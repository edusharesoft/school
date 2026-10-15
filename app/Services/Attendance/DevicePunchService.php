<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendancePunch;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * Ingests punches pushed by biometric / RFID / QR terminals (or the vendor's middleware, e.g. ZKTeco ADMS
 * bridge) and routes them to staff or student attendance. Idempotent: each (branch, code, timestamp) is
 * processed once, so devices may safely re-send their buffer.
 */
class DevicePunchService
{
    public function __construct(
        protected StaffAttendanceService $staff,
        protected StudentAttendanceService $students,
    ) {}

    /**
     * @param  array<int,array{code:string,time:string}>  $punches
     * @return array{applied:int, duplicate:int, unknown:int, ignored:int, results:array<int,array>}
     */
    public function ingest(AttendanceDevice $device, array $punches): array
    {
        $summary = ['applied' => 0, 'duplicate' => 0, 'unknown' => 0, 'ignored' => 0, 'results' => []];

        // Oldest first so check-in / check-out order is correct.
        usort($punches, fn ($a, $b) => strcmp((string) $a['time'], (string) $b['time']));

        foreach ($punches as $punch) {
            $code = strtoupper(trim((string) $punch['code']));
            $at = CarbonImmutable::parse($punch['time'], config('app.timezone'))->setTimezone(config('app.timezone'))->startOfMinute();

            if (AttendancePunch::query()->where('person_code', $code)->where('punched_at', $at)->exists()) {
                $summary['duplicate']++;
                $summary['results'][] = ['code' => $code, 'time' => $at->toIso8601String(), 'result' => 'duplicate'];
                continue;
            }

            [$type, $person, $outcome] = $this->apply($code, $at, $device->type === 'biometric' ? 'biometric' : ($device->type === 'rfid' ? 'id_card' : 'qr'));

            $result = match (true) {
                $person === null => 'unknown',
                $outcome['ok'] && $outcome['state'] !== 'duplicate' => 'applied',
                $outcome['state'] === 'duplicate' => 'duplicate',
                default => 'ignored',
            };

            try {
                AttendancePunch::query()->create([
                    'attendance_device_id' => $device->id,
                    'person_code' => $code,
                    'person_type' => $type,
                    'person_id' => $person?->id,
                    'punched_at' => $at,
                    'result' => $result,
                    'message' => mb_substr($outcome['message'] ?? __('Unknown ID :code', ['code' => $code]), 0, 255),
                ]);
            } catch (QueryException) {
                $result = 'duplicate'; // a concurrent request stored the same punch first
            }

            $summary[$result]++;
            $summary['results'][] = ['code' => $code, 'time' => $at->toIso8601String(), 'result' => $result, 'message' => $outcome['message'] ?? null];
        }

        $device->forceFill(['last_seen_at' => now(), 'last_ip' => request()?->ip()])->save();

        return $summary;
    }

    /**
     * Route one code to an employee (check-in/out) or a student (daily attendance).
     *
     * @return array{0:?string, 1:Employee|Student|null, 2:array}
     */
    public function apply(string $code, CarbonImmutable $at, string $method): array
    {
        [$type, $person] = $this->identify($code);

        return [$type, $person, match ($type) {
            'employee' => $this->staff->punch($person, $at, $method),
            'student' => $this->students->scan($person, null, $method, $at),
            default => ['ok' => false, 'state' => 'unknown', 'message' => __('Unknown ID :code', ['code' => $code])],
        }];
    }

    /**
     * Find who an ID belongs to (employee codes first, then student codes) inside the current branch.
     *
     * @return array{0:?string, 1:Employee|Student|null}
     */
    public function identify(string $code): array
    {
        $code = strtoupper(trim($code));

        if ($employee = Employee::query()->where('employee_code', $code)->first()) {
            return ['employee', $employee];
        }
        if ($student = Student::query()->where('student_code', $code)->first()) {
            return ['student', $student];
        }

        return [null, null];
    }
}
