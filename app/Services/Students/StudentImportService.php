<?php

namespace App\Services\Students;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudyGroup;
use App\Models\Students\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SplFileObject;

/**
 * Bulk student import from CSV (UTF-8, Excel "CSV UTF-8" export works; Bangla text supported).
 *
 * All-or-nothing: every row is validated first; if any row fails, nothing is saved and a
 * per-row error report is returned. With $dryRun = true only validation runs.
 */
class StudentImportService
{
    public const COLUMNS = [
        'name', 'name_bn', 'gender', 'date_of_birth', 'birth_registration_no', 'blood_group', 'religion',
        'phone', 'present_address', 'admission_date', 'class_code', 'section', 'group_code', 'roll_no',
        'guardian_name', 'guardian_phone', 'guardian_relation',
    ];

    public const MAX_ROWS = 2000;

    public function __construct(protected StudentService $students) {}

    /**
     * @return array{ok:bool, imported:int, total:int, errors:array<int,array<int,string>>}
     */
    public function import(string $path, AcademicSession $session, bool $dryRun = false): array
    {
        $rows = $this->read($path);
        $classes = SchoolClass::query()->with('sections')->get()->keyBy(fn ($c) => strtoupper($c->code));
        $groups = StudyGroup::query()->get()->keyBy(fn ($g) => strtoupper($g->code));

        $errors = [];
        $prepared = [];
        $seenRolls = [];

        foreach ($rows as $line => $row) {
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:191'],
                'name_bn' => ['nullable', 'string', 'max:191'],
                'gender' => ['required', Rule::in(Student::GENDERS)],
                'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
                'birth_registration_no' => ['nullable', 'regex:/^\d{17}$/'],
                'blood_group' => ['nullable', Rule::in(Student::BLOOD_GROUPS)],
                'religion' => ['nullable', 'string', 'max:30'],
                'phone' => ['nullable', 'regex:/^\+?\d{6,15}$/'],
                'present_address' => ['nullable', 'string', 'max:1000'],
                'admission_date' => ['nullable', 'date_format:Y-m-d'],
                'class_code' => ['required', 'string'],
                'section' => ['required', 'string'],
                'group_code' => ['nullable', 'string'],
                'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
                'guardian_name' => ['required', 'string', 'max:191'],
                'guardian_phone' => ['required', 'regex:/^\+?\d{6,15}$/'],
                'guardian_relation' => ['required', Rule::in(\App\Models\Students\Guardian::RELATIONS)],
            ]);

            $rowErrors = $validator->errors()->all();

            $class = $classes->get(strtoupper((string) ($row['class_code'] ?? '')));
            $section = $class?->sections->first(fn ($s) => strcasecmp($s->name, (string) ($row['section'] ?? '')) === 0);
            $group = ! empty($row['group_code']) ? $groups->get(strtoupper($row['group_code'])) : null;

            if (! empty($row['class_code']) && ! $class) {
                $rowErrors[] = __('Unknown class code ":code".', ['code' => $row['class_code']]);
            }
            if ($class && ! $section) {
                $rowErrors[] = __('Section ":section" does not exist in this class.', ['section' => $row['section'] ?? '']);
            }
            if (! empty($row['group_code']) && ! $group) {
                $rowErrors[] = __('Unknown group code ":code".', ['code' => $row['group_code']]);
            }
            if ($class?->has_groups && ! $group) {
                $rowErrors[] = __('A group is required for this class.');
            }
            if (! empty($row['birth_registration_no'])
                && Student::query()->where('birth_registration_no', $row['birth_registration_no'])->exists()) {
                $rowErrors[] = __('A student with this birth registration number already exists.');
            }
            if ($section && ! empty($row['roll_no'])) {
                $key = $section->id.'#'.$row['roll_no'];
                if (isset($seenRolls[$key]) || $this->students->rollTaken($section->id, $session->id, (int) $row['roll_no'])) {
                    $rowErrors[] = __('Roll :roll is already taken in this section.', ['roll' => $row['roll_no']]);
                }
                $seenRolls[$key] = true;
            }

            if ($rowErrors) {
                $errors[$line] = $rowErrors;
                continue;
            }

            $prepared[$line] = [$row, $class, $section, $group];
        }

        if ($errors || $dryRun) {
            return ['ok' => ! $errors, 'imported' => 0, 'total' => count($rows), 'errors' => $errors];
        }

        $imported = 0;
        try {
            DB::transaction(function () use ($prepared, $session, &$imported, &$errors) {
                foreach ($prepared as $line => [$row, $class, $section, $group]) {
                    try {
                        $this->students->admit(
                            array_filter([
                                'name' => $row['name'], 'name_bn' => $row['name_bn'] ?? null, 'gender' => $row['gender'],
                                'date_of_birth' => $row['date_of_birth'], 'birth_registration_no' => $row['birth_registration_no'] ?? null,
                                'blood_group' => $row['blood_group'] ?? null, 'religion' => $row['religion'] ?? null,
                                'phone' => $row['phone'] ?? null, 'present_address' => $row['present_address'] ?? null,
                                'admission_date' => $row['admission_date'] ?? null,
                            ], fn ($v) => $v !== null && $v !== ''),
                            [['name' => $row['guardian_name'], 'phone' => $row['guardian_phone'], 'relation' => $row['guardian_relation'], 'is_primary' => true]],
                            [
                                'academic_session_id' => $session->id, 'school_class_id' => $class->id, 'section_id' => $section->id,
                                'study_group_id' => $group?->id, 'roll_no' => $row['roll_no'] ?? null,
                            ],
                        );
                        $imported++;
                    } catch (ValidationException $e) {
                        $errors[$line] = $e->validator->errors()->all();
                    }
                }

                if ($errors) {
                    throw new \RuntimeException('rollback');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'rollback') {
                throw $e;
            }
            $imported = 0;
        }

        return ['ok' => ! $errors, 'imported' => $imported, 'total' => count($rows), 'errors' => $errors];
    }

    /**
     * @return array<int, array<string,string>> keyed by CSV line number (header = line 1)
     */
    public function read(string $path): array
    {
        $file = new SplFileObject($path);
        // No SKIP_EMPTY so that reported line numbers match what the user sees in Excel.
        $file->setFlags(SplFileObject::READ_CSV);

        $header = null;
        $rows = [];
        $line = 0;

        foreach ($file as $cells) {
            $line++;
            if ($cells === [null] || $cells === false) {
                continue;
            }
            if ($header === null) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]); // strip BOM
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $cells);
                $missing = array_diff(['name', 'gender', 'date_of_birth', 'class_code', 'section', 'guardian_name', 'guardian_phone', 'guardian_relation'], $header);
                if ($missing) {
                    throw ValidationException::withMessages(['file' => __('Missing required columns: :cols', ['cols' => implode(', ', $missing)])]);
                }

                continue;
            }

            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }
            $rows[$line] = array_map(fn ($v) => $v === '' ? null : $v, $row);

            if (count($rows) > self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => __('A file may contain at most :max rows.', ['max' => self::MAX_ROWS])]);
            }
        }

        if ($header === null) {
            throw ValidationException::withMessages(['file' => __('The file is empty.')]);
        }

        return $rows;
    }
}
