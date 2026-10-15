<?php

namespace App\Services\Exams;

use App\Models\Academic\Room;
use App\Models\Academic\Section;
use App\Models\Exams\Examination;
use App\Models\Students\StudentEnrollment;
use Illuminate\Support\Collection;

/** Admit cards (with the attendance-eligibility rule) and seat plans of an exam. */
class ExamDocumentService
{
    public function __construct(protected ResultService $results) {}

    /**
     * Candidates of a section with their papers and eligibility.
     *
     * @return Collection<int,array{enrollment:StudentEnrollment, papers:Collection, attendance:?float, eligible:bool}>
     */
    public function admitCards(Examination $exam, Section $section): Collection
    {
        $papers = $exam->subjects()->with('subject:id,name,code')->where('school_class_id', $section->school_class_id)->get()
            ->sortBy(fn ($p) => ($p->exam_date?->toDateString() ?? '9999').' '.$p->starts_at.' '.$p->sort_order)->values();

        $enrollments = StudentEnrollment::query()->with(['student:id,name,name_bn,student_code,photo_path,date_of_birth', 'studyGroup:id,name'])
            ->where('academic_session_id', $exam->academic_session_id)->where('section_id', $section->id)
            ->whereNotIn('status', MarkEntryService::LEFT)->orderBy('roll_no')->get();

        $attendance = $this->results->attendance($enrollments->pluck('student_id')->all(), $exam);

        return $enrollments->map(function (StudentEnrollment $e) use ($papers, $attendance, $exam) {
            $pct = $attendance[$e->student_id] ?? null;

            return [
                'enrollment' => $e,
                'papers' => $papers->filter(fn ($p) => ! $p->study_group_id || (int) $p->study_group_id === (int) $e->study_group_id)->values(),
                'attendance' => $pct,
                // No register yet (e.g. new admission) never blocks a card; a recorded % below the minimum does.
                'eligible' => $exam->min_attendance === null || $pct === null || $pct >= $exam->min_attendance,
            ];
        });
    }

    /**
     * Seat plan: candidates of every class in the exam, interleaved class by class (neighbours sit different
     * papers), filled into the chosen rooms by capacity.
     *
     * @param  int[]  $roomIds  empty = all active rooms
     * @return array{rooms:Collection, unseated:Collection}
     */
    public function seatPlan(Examination $exam, array $roomIds = []): array
    {
        $classIds = $exam->subjects()->distinct()->pluck('school_class_id');
        $queues = StudentEnrollment::query()->with(['student:id,name,student_code', 'schoolClass:id,name,numeric_order', 'section:id,name'])
            ->where('academic_session_id', $exam->academic_session_id)->whereIn('school_class_id', $classIds)
            ->whereNotIn('status', MarkEntryService::LEFT)->get()
            ->sortBy(fn ($e) => sprintf('%05d-%s-%06d', $e->schoolClass?->numeric_order ?? 0, $e->section?->name, $e->roll_no ?? 0))
            ->groupBy('school_class_id')->map(fn ($g) => $g->values())->values();

        // Round-robin across classes.
        $order = collect();
        for ($i = 0, $max = $queues->max(fn ($q) => $q->count()) ?? 0; $i < $max; $i++) {
            foreach ($queues as $queue) {
                if (isset($queue[$i])) {
                    $order->push($queue[$i]);
                }
            }
        }

        $rooms = Room::query()->where('is_active', true)->where('capacity', '>', 0)
            ->when($roomIds, fn ($q) => $q->whereIn('id', $roomIds))->orderBy('code')->orderBy('name')->get();

        $plan = collect();
        $cursor = 0;
        foreach ($rooms as $room) {
            $seats = $order->slice($cursor, (int) $room->capacity)->values();
            if ($seats->isEmpty()) {
                break;
            }
            $cursor += $seats->count();
            $plan->push(['room' => $room, 'seats' => $seats]);
        }

        return ['rooms' => $plan, 'unseated' => $order->slice($cursor)->values()];
    }
}
