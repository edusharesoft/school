<?php

namespace App\Services\Dashboard;

use App\Enums\AttendanceStatus;
use App\Enums\RecordStatus;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Models\Exams\GradingScale;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard statistics computed with SQL aggregation (never by loading records into memory).
 * Every number comes from real rows. Later parts register more metrics here
 * (students, attendance, fees, exams) following the same pattern.
 */
class DashboardService
{
    public const CACHE_TTL = 300;

    public function __construct(protected TenantContext $tenant) {}

    /**
     * @param  array{branch_id?:int|null,from?:string|null,to?:string|null}  $filters
     */
    public function central(array $filters): array
    {
        [$from, $to] = $this->range($filters);
        $branchIds = $this->visibleBranchIds($filters['branch_id'] ?? null);

        $key = 'dash:central:'.($this->tenant->institutionId() ?? 'p').':'.md5(json_encode([$branchIds, $from->toDateString(), $to->toDateString()]));

        return Cache::remember($key, self::CACHE_TTL, function () use ($branchIds, $from, $to) {
            $branches = Branch::query()->whereIn('id', $branchIds)->ordered()->get(['id', 'name', 'code', 'status']);

            return [
                'generated_at' => now()->toIso8601String(),
                'cards' => $this->cards($branchIds, $branches) + $this->studentCards($branchIds, $from, $to) + $this->attendanceCards($branchIds),
                'charts' => [
                    'branch_attendance' => $this->branchAttendanceToday($branches),
                    'attendance_trend' => $this->attendanceTrend($branchIds, $from, $to),
                    'branch_students' => $this->studentsPerBranch($branches),
                    'admission_trend' => $this->admissionTrend($branchIds, $from, $to),
                    'branch_accounts' => $this->branchAccounts($branches),
                    'login_trend' => $this->loginTrend($branchIds, $from, $to),
                    'branch_status' => [
                        'labels' => [__('Active'), __('Inactive')],
                        'data' => [
                            $branches->where('status', RecordStatus::Active)->count(),
                            $branches->where('status', '!=', RecordStatus::Active)->count(),
                        ],
                    ],
                    'departments' => $this->departmentsPerBranch($branches),
                ],
                'recent_activity' => $this->recentActivity($branchIds, true),
            ];
        });
    }

    /** Dashboard for the branch in the current context. */
    public function branch(array $filters): array
    {
        $branch = $this->tenant->branch();
        [$from, $to] = $this->range($filters);

        $key = 'dash:branch:'.$branch->id.':'.md5($from->toDateString().$to->toDateString());

        return Cache::remember($key, self::CACHE_TTL, function () use ($branch, $from, $to) {
            $ids = [$branch->id];

            return [
                'generated_at' => now()->toIso8601String(),
                'cards' => $this->cards($ids, collect([$branch])) + $this->studentCards($ids, $from, $to) + $this->attendanceCards($ids) + [
                    'pending_leaves' => LeaveApplication::query()->where('status', 'pending')->count(),
                    'open_absence_alerts' => AttendanceAlert::query()->where('status', 'open')->count(),
                ] + $this->feeCards(),
                'charts' => [
                    'attendance_trend' => $this->attendanceTrend($ids, $from, $to),
                    'class_attendance' => $this->classAttendanceToday(),
                    'staff_status_today' => $this->staffStatusToday($ids),
                    'students_by_class' => $this->studentsByClass(),
                    'admission_trend' => $this->admissionTrend($ids, $from, $to),
                    'login_trend' => $this->loginTrend($ids, $from, $to),
                    'accounts_by_type' => $this->accountsByType($ids),
                ] + $this->examPerformance() + ['fee_collection' => $this->feeCollection()],
                'recent_activity' => $this->recentActivity($ids, false),
            ];
        });
    }

    /** Rows for CSV export of the central summary. */
    public function branchSummaryRows(array $filters): Collection
    {
        [$from, $to] = $this->range($filters);
        $branchIds = $this->visibleBranchIds($filters['branch_id'] ?? null);

        $accounts = $this->accountCountsByBranch($branchIds);
        $departments = Department::query()->whereIn('branch_id', $branchIds)
            ->select('branch_id', DB::raw('COUNT(*) as total'))->groupBy('branch_id')->pluck('total', 'branch_id');
        $logins = AuditLog::query()->where('event', 'auth.login')->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->select('branch_id', DB::raw('COUNT(*) as total'))->groupBy('branch_id')->pluck('total', 'branch_id');

        return Branch::query()->whereIn('id', $branchIds)->ordered()->get()->map(fn (Branch $b) => [
            'branch' => $b->name,
            'code' => $b->code,
            'host' => $b->host(),
            'status' => $b->status->value,
            'staff_accounts' => (int) ($accounts[$b->id]['staff'] ?? 0),
            'teacher_accounts' => (int) ($accounts[$b->id]['teacher'] ?? 0),
            'student_accounts' => (int) ($accounts[$b->id]['student'] ?? 0),
            'parent_accounts' => (int) ($accounts[$b->id]['parent'] ?? 0),
            'departments' => (int) ($departments[$b->id] ?? 0),
            'logins_in_period' => (int) ($logins[$b->id] ?? 0),
        ]);
    }

    // ------------------------------------------------------------------ building blocks

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    public function range(array $filters): array
    {
        $to = ! empty($filters['to']) ? CarbonImmutable::parse($filters['to']) : CarbonImmutable::today();
        $from = ! empty($filters['from']) ? CarbonImmutable::parse($filters['from']) : $to->subDays(13);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 180) {
            $from = $to->subDays(180);
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /** Branch ids visible in the current central context, optionally narrowed to one branch. */
    public function visibleBranchIds(?int $branchId = null): array
    {
        $query = Branch::query();
        if ($this->tenant->institutionId()) {
            $query->where('institution_id', $this->tenant->institutionId());
        }
        if ($branchId) {
            $query->whereKey($branchId);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    protected function cards(array $branchIds, Collection $branches): array
    {
        $byType = DB::table('branch_user')
            ->join('users', 'users.id', '=', 'branch_user.user_id')
            ->whereIn('branch_user.branch_id', $branchIds)
            ->whereNull('users.deleted_at')
            ->select('users.user_type', DB::raw('COUNT(DISTINCT users.id) as total'))
            ->groupBy('users.user_type')
            ->pluck('total', 'user_type');

        $today = CarbonImmutable::today();
        $loginsToday = AuditLog::query()->whereIn('branch_id', $branchIds)
            ->whereIn('event', ['auth.login', 'auth.failed'])
            ->where('created_at', '>=', $today)
            ->select('event', DB::raw('COUNT(*) as total'))->groupBy('event')->pluck('total', 'event');

        return [
            'branches_total' => $branches->count(),
            'branches_active' => $branches->where('status', RecordStatus::Active)->count(),
            'branches_inactive' => $branches->where('status', '!=', RecordStatus::Active)->count(),
            'staff_accounts' => (int) ($byType['staff'] ?? 0),
            'teacher_accounts' => (int) ($byType['teacher'] ?? 0),
            'student_accounts' => (int) ($byType['student'] ?? 0),
            'parent_accounts' => (int) ($byType['parent'] ?? 0),
            'departments' => Department::query()->whereIn('branch_id', $branchIds)->count(),
            'logins_today' => (int) ($loginsToday['auth.login'] ?? 0),
            'failed_logins_today' => (int) ($loginsToday['auth.failed'] ?? 0),
        ];
    }

    /** Student figures (Part 3A). "Enrolled" = active enrollment in a session currently marked current. */
    protected function studentCards(array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byStatus = Student::query()->whereIn('branch_id', $branchIds)
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');

        $byGender = Student::query()->whereIn('branch_id', $branchIds)->where('status', 'active')
            ->select('gender', DB::raw('COUNT(*) as total'))->groupBy('gender')->pluck('total', 'gender');

        return [
            'students_active' => (int) ($byStatus['active'] ?? 0),
            'students_male' => (int) ($byGender['male'] ?? 0),
            'students_female' => (int) ($byGender['female'] ?? 0),
            'students_left' => (int) (($byStatus['withdrawn'] ?? 0) + ($byStatus['transferred'] ?? 0)),
            'new_admissions' => Student::query()->whereIn('branch_id', $branchIds)
                ->whereBetween('admission_date', [$from->toDateString(), $to->toDateString()])->count(),
        ];
    }

    protected function studentsPerBranch(Collection $branches): array
    {
        $counts = Student::query()->whereIn('branch_id', $branches->pluck('id'))->where('status', 'active')
            ->select('branch_id', DB::raw('COUNT(*) as total'))->groupBy('branch_id')->pluck('total', 'branch_id');

        return [
            'labels' => $branches->pluck('name')->all(),
            'branch_ids' => $branches->pluck('id')->all(),
            'data' => $branches->map(fn ($b) => (int) ($counts[$b->id] ?? 0))->all(),
        ];
    }

    /** Current-session enrollments per class for the branch in context. */
    protected function studentsByClass(): array
    {
        $rows = StudentEnrollment::query()
            ->join('academic_sessions', 'academic_sessions.id', '=', 'student_enrollments.academic_session_id')
            ->join('school_classes', 'school_classes.id', '=', 'student_enrollments.school_class_id')
            ->where('academic_sessions.is_current', true)
            ->where('student_enrollments.status', 'enrolled')
            ->select('school_classes.name', 'school_classes.numeric_order', DB::raw('COUNT(*) as total'))
            ->groupBy('school_classes.id', 'school_classes.name', 'school_classes.numeric_order')
            ->orderBy('school_classes.numeric_order')
            ->get();

        return ['labels' => $rows->pluck('name')->all(), 'data' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
    }

    protected function admissionTrend(array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Student::query()->whereIn('branch_id', $branchIds)
            ->whereBetween('admission_date', [$from->toDateString(), $to->toDateString()])
            ->select('admission_date', DB::raw('COUNT(*) as total'))->groupBy('admission_date')
            ->pluck('total', 'admission_date');

        $index = [];
        foreach ($rows as $date => $total) {
            $index[substr((string) $date, 0, 10)] = (int) $total;
        }

        $labels = [];
        $data = [];
        foreach (CarbonPeriod::create($from->startOfDay(), $to->startOfDay()) as $day) {
            $labels[] = $day->format('d M');
            $data[] = $index[$day->toDateString()] ?? 0;
        }

        return ['labels' => $labels, 'datasets' => [['label' => __('New admissions'), 'data' => $data]]];
    }

    /** @return array<int,array<string,int>> branch_id => [user_type => count] */
    protected function accountCountsByBranch(array $branchIds): array
    {
        $rows = DB::table('branch_user')
            ->join('users', 'users.id', '=', 'branch_user.user_id')
            ->whereIn('branch_user.branch_id', $branchIds)
            ->whereNull('users.deleted_at')
            ->select('branch_user.branch_id', 'users.user_type', DB::raw('COUNT(*) as total'))
            ->groupBy('branch_user.branch_id', 'users.user_type')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->branch_id][$row->user_type] = (int) $row->total;
        }

        return $out;
    }

    protected function branchAccounts(Collection $branches): array
    {
        $counts = $this->accountCountsByBranch($branches->pluck('id')->all());
        $types = ['staff' => __('Staff'), 'teacher' => __('Teacher'), 'student' => __('Student'), 'parent' => __('Parent/Guardian')];

        return [
            'labels' => $branches->pluck('name')->all(),
            'branch_ids' => $branches->pluck('id')->all(),
            'datasets' => collect($types)->map(fn ($label, $type) => [
                'label' => $label,
                'data' => $branches->map(fn ($b) => $counts[$b->id][$type] ?? 0)->all(),
            ])->values()->all(),
        ];
    }

    protected function accountsByType(array $branchIds): array
    {
        $counts = $this->accountCountsByBranch($branchIds)[$branchIds[0]] ?? [];
        $types = ['staff' => __('Staff'), 'teacher' => __('Teacher'), 'student' => __('Student'), 'parent' => __('Parent/Guardian')];

        return [
            'labels' => array_values($types),
            'data' => array_map(fn ($type) => $counts[$type] ?? 0, array_keys($types)),
        ];
    }

    // ------------------------------------------------------------------ attendance (Part 3B)

    /** Today's student / teacher / employee attendance figures. "Present" includes late and half-day. */
    protected function attendanceCards(array $branchIds): array
    {
        $today = CarbonImmutable::today()->toDateString();

        $students = StudentAttendance::query()->whereIn('branch_id', $branchIds)->whereDate('date', $today)->where('slot', 'day')
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->toBase()->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();

        $staff = StaffAttendance::query()->join('employees', 'employees.id', '=', 'staff_attendances.employee_id')
            ->whereIn('staff_attendances.branch_id', $branchIds)->whereDate('staff_attendances.date', $today)
            ->select('employees.employee_type', 'staff_attendances.status', DB::raw('COUNT(*) as total'))
            ->groupBy('employees.employee_type', 'staff_attendances.status')->toBase()->get(); // raw rows: status stays a string

        $employees = Employee::query()->whereIn('branch_id', $branchIds)->where('status', 'active')
            ->select('employee_type', DB::raw('COUNT(*) as total'))->groupBy('employee_type')->pluck('total', 'employee_type');

        $present = fn (array $c) => ($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['half_day'] ?? 0);
        $teacher = $staff->where('employee_type', 'teacher')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
        $allStaff = $staff->groupBy('status')->map(fn ($rows) => (int) $rows->sum('total'))->all();

        return [
            'students_present_today' => $present($students),
            'students_absent_today' => (int) ($students['absent'] ?? 0),
            'students_late_today' => (int) ($students['late'] ?? 0),
            'student_attendance_pct_today' => AttendanceStatus::percentage($students) ?? 0,
            'teachers_total' => (int) ($employees['teacher'] ?? 0),
            'employees_total' => (int) $employees->sum(),
            'teachers_present_today' => $present($teacher),
            'teachers_absent_today' => (int) ($teacher['absent'] ?? 0),
            'staff_present_today' => $present($allStaff),
            'staff_attendance_pct_today' => AttendanceStatus::percentage($allStaff) ?? 0,
        ];
    }

    /** Daily attendance % of students and of teachers/staff over the period. */
    protected function attendanceTrend(array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $collect = function ($query, string $dateColumn) use ($from, $to) {
            $rows = $query->whereDate($dateColumn, '>=', $from->toDateString())->whereDate($dateColumn, '<=', $to->toDateString())
                ->select(DB::raw("DATE({$dateColumn}) as day"), 'status', DB::raw('COUNT(*) as total'))
                ->groupBy('day', 'status')->get();
            $out = [];
            foreach ($rows as $r) {
                $out[substr((string) $r->day, 0, 10)][$r->status instanceof AttendanceStatus ? $r->status->value : (string) $r->status] = (int) $r->total;
            }

            return $out;
        };

        $students = $collect(StudentAttendance::query()->toBase()->whereIn('branch_id', $branchIds)->where('slot', 'day'), 'date');
        $staff = $collect(StaffAttendance::query()->toBase()->whereIn('branch_id', $branchIds), 'date');

        $labels = [];
        $s = [];
        $t = [];
        foreach (CarbonPeriod::create($from->startOfDay(), $to->startOfDay()) as $day) {
            $d = $day->toDateString();
            $labels[] = $day->format('d M');
            $s[] = isset($students[$d]) ? AttendanceStatus::percentage($students[$d]) : null;
            $t[] = isset($staff[$d]) ? AttendanceStatus::percentage($staff[$d]) : null;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => __('Students %'), 'data' => $s],
                ['label' => __('Teachers & staff %'), 'data' => $t],
            ],
        ];
    }

    /** Today's student attendance % per branch (drill-down to the branch). */
    protected function branchAttendanceToday(Collection $branches): array
    {
        $rows = StudentAttendance::query()->whereIn('branch_id', $branches->pluck('id'))
            ->whereDate('date', CarbonImmutable::today()->toDateString())->where('slot', 'day')
            ->select('branch_id', 'status', DB::raw('COUNT(*) as total'))->groupBy('branch_id', 'status')->get()
            ->groupBy('branch_id');

        return [
            'labels' => $branches->pluck('name')->all(),
            'branch_ids' => $branches->pluck('id')->all(),
            'data' => $branches->map(fn ($b) => isset($rows[$b->id])
                ? (AttendanceStatus::percentage($rows[$b->id]->mapWithKeys(fn ($r) => [$r->getRawOriginal('status') => (int) $r->total])->all()) ?? 0)
                : 0)->all(),
        ];
    }

    /** Today's attendance % per class in the branch in context. */
    /** Fee figures of the branch from recorded receipts and invoices. */
    protected function feeCards(): array
    {
        $today = CarbonImmutable::today();

        return [
            'fees_collected_today' => round((float) FeePayment::query()->where('status', 'completed')->whereDate('date', $today->toDateString())->sum('amount'), 2),
            'fees_collected_month' => round((float) FeePayment::query()->where('status', 'completed')->whereDate('date', '>=', $today->startOfMonth()->toDateString())->sum('amount'), 2),
            'fees_outstanding' => round((float) FeeInvoice::query()->open()->sum('balance'), 2),
            'invoices_overdue' => FeeInvoice::query()->overdue($today)->count(),
        ];
    }

    /** Billed vs collected, last 6 months. */
    protected function feeCollection(): array
    {
        $m = app(\App\Services\Finance\FeeReportService::class)->monthly(6);

        return ['labels' => $m['labels'], 'datasets' => [['label' => __('Billed'), 'data' => $m['billed']], ['label' => __('Collected'), 'data' => $m['collected']]]];
    }

    /** Grade distribution and class-wise pass rate of the latest published exam of the branch. */
    protected function examPerformance(): array
    {
        $empty = ['exam_grades' => ['labels' => [], 'data' => []], 'exam_pass_by_class' => ['labels' => [], 'data' => []]];
        $exam = Examination::query()->where('status', 'published')->latest('published_at')->first();
        if (! $exam) {
            return $empty;
        }

        $rows = ExamResult::query()->join('school_classes', 'school_classes.id', '=', 'exam_results.school_class_id')
            ->where('exam_results.examination_id', $exam->id)
            ->select('school_classes.id', 'school_classes.name', 'school_classes.numeric_order', 'exam_results.status', 'exam_results.grade')
            ->orderBy('school_classes.numeric_order')->toBase()->get();

        $order = collect(GradingScale::query()->withTrashed()->find($exam->grading_scale_id)?->grades ?? [])->pluck('grade')->values();
        $grades = $rows->where('status', 'pass')->countBy('grade')->sortBy(fn ($n, $g) => $order->search($g) === false ? 99 : $order->search($g));
        $classes = $rows->groupBy('id')->map(function ($r) {
            $ranked = $r->whereIn('status', ['pass', 'fail'])->count();

            return ['name' => $r->first()->name, 'pct' => $ranked ? round($r->where('status', 'pass')->count() / $ranked * 100, 1) : 0];
        })->values();

        return [
            'exam_grades' => ['labels' => $grades->keys()->all(), 'data' => $grades->values()->all(), 'title' => $exam->name],
            'exam_pass_by_class' => ['labels' => $classes->pluck('name')->all(), 'data' => $classes->pluck('pct')->all(), 'title' => $exam->name],
        ];
    }

    protected function classAttendanceToday(): array
    {
        $rows = StudentAttendance::query()
            ->join('school_classes', 'school_classes.id', '=', 'student_attendances.school_class_id')
            ->whereDate('student_attendances.date', CarbonImmutable::today()->toDateString())->where('student_attendances.slot', 'day')
            ->select('school_classes.id', 'school_classes.name', 'school_classes.numeric_order', 'student_attendances.status', DB::raw('COUNT(*) as total'))
            ->groupBy('school_classes.id', 'school_classes.name', 'school_classes.numeric_order', 'student_attendances.status')
            ->orderBy('school_classes.numeric_order')->toBase()->get()->groupBy('id');

        return [
            'labels' => $rows->map(fn ($r) => $r->first()->name)->values()->all(),
            'data' => $rows->map(fn ($r) => AttendanceStatus::percentage($r->pluck('total', 'status')->map(fn ($v) => (int) $v)->all()) ?? 0)->values()->all(),
        ];
    }

    protected function staffStatusToday(array $branchIds): array
    {
        $counts = StaffAttendance::query()->whereIn('branch_id', $branchIds)->whereDate('date', CarbonImmutable::today()->toDateString())
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $active = Employee::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->count();

        $labels = [];
        $data = [];
        foreach (AttendanceStatus::cases() as $case) {
            $labels[] = $case->label();
            $data[] = (int) ($counts[$case->value] ?? 0);
        }
        $labels[] = __('Not marked');
        $data[] = max(0, $active - array_sum($data));

        return ['labels' => $labels, 'data' => $data];
    }

    protected function loginTrend(array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = AuditLog::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('event', ['auth.login', 'auth.failed'])
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as day'), 'event', DB::raw('COUNT(*) as total'))
            ->groupBy('day', 'event')
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $index[$row->event][(string) $row->day] = (int) $row->total;
        }

        $labels = [];
        $ok = [];
        $failed = [];
        foreach (CarbonPeriod::create($from->startOfDay(), $to->startOfDay()) as $day) {
            $d = $day->toDateString();
            $labels[] = $day->format('d M');
            $ok[] = $index['auth.login'][$d] ?? 0;
            $failed[] = $index['auth.failed'][$d] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => __('Successful sign-ins'), 'data' => $ok],
                ['label' => __('Failed sign-ins'), 'data' => $failed],
            ],
        ];
    }

    protected function departmentsPerBranch(Collection $branches): array
    {
        $counts = Department::query()->whereIn('branch_id', $branches->pluck('id'))
            ->select('branch_id', DB::raw('COUNT(*) as total'))->groupBy('branch_id')->pluck('total', 'branch_id');

        return [
            'labels' => $branches->pluck('name')->all(),
            'data' => $branches->map(fn ($b) => (int) ($counts[$b->id] ?? 0))->all(),
        ];
    }

    protected function recentActivity(array $branchIds, bool $includeInstitutionLevel): array
    {
        return AuditLog::query()
            ->with(['user:id,name', 'branch:id,name'])
            ->where(function ($q) use ($branchIds, $includeInstitutionLevel) {
                $q->whereIn('branch_id', $branchIds);
                if ($includeInstitutionLevel) {
                    $q->orWhere(function ($q) {
                        $q->whereNull('branch_id');
                        if ($this->tenant->institutionId()) {
                            $q->where('institution_id', $this->tenant->institutionId());
                        }
                    });
                }
            })
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'subject' => $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : null,
                'user' => $log->user?->name,
                'branch' => $log->branch?->name,
                'at' => $log->created_at?->toIso8601String(),
                'ago' => $log->created_at?->diffForHumans(),
            ])->all();
    }
}
