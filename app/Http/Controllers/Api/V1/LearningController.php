<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Learning\Assignment;
use App\Models\Learning\AssignmentSubmission;
use App\Models\Learning\LearningMaterial;
use App\Models\Learning\OnlineClass;
use App\Models\Learning\Quiz;
use App\Models\Learning\QuizAttempt;
use App\Models\Students\Student;
use App\Services\Learning\LearningProgress;
use App\Services\Portal\PortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Read-only learning data for the signed-in student (or, for a guardian, each of their children):
 *  GET /api/v1/my/assignments     homework & assignments with the hand-in state, marks and feedback
 *  GET /api/v1/my/online-classes  upcoming classes (join link only from 15 minutes before) and the last 30 days
 *  GET /api/v1/my/materials       shared learning materials (links; files are downloaded in the portal)
 *  GET /api/v1/my/quizzes         quizzes with the best score (taking a quiz happens in the portal)
 *  GET /api/v1/my/learning        the learning-progress summary
 * Handing in work and taking quizzes stay in the web portal, where files, timers and autosave are handled.
 */
class LearningController extends Controller
{
    use ApiResponse;

    public function assignments(Request $request, PortalService $portal): JsonResponse
    {
        return $this->perStudent($request, $portal, function (Student $s) {
            $e = $s->currentEnrollment;
            $items = $e ? Assignment::query()->visibleToStudents()->forEnrollment($e)->with('subject:id,name,name_bn')->orderBy('due_at')->get() : collect();
            $subs = AssignmentSubmission::query()->where('student_id', $s->id)->whereIn('assignment_id', $items->pluck('id'))->get()->keyBy('assignment_id');

            return $items->map(function (Assignment $a) use ($subs) {
                $sub = $subs->get($a->id);

                return [
                    'id' => $a->id, 'kind' => $a->kind, 'title' => $a->title, 'instructions' => $a->instructions, 'subject' => $a->subject?->displayName(),
                    'due_at' => $a->due_at->toIso8601String(), 'max_marks' => $a->max_marks !== null ? $a->max_marks + 0 : null, 'status' => $a->status,
                    'submission_mode' => $a->submission_mode, 'allow_late' => $a->allow_late, 'has_attachment' => (bool) $a->attachment_path,
                    'submission' => $sub ? [
                        'status' => $sub->status, 'submitted_at' => $sub->submitted_at?->toIso8601String(), 'is_late' => $sub->is_late,
                        'marks' => $sub->marks !== null ? $sub->marks + 0 : null, 'feedback' => $sub->feedback,
                    ] : null,
                ];
            })->values();
        });
    }

    public function onlineClasses(Request $request, PortalService $portal): JsonResponse
    {
        return $this->perStudent($request, $portal, function (Student $s) {
            $e = $s->currentEnrollment;
            if (! $e) {
                return collect();
            }

            return OnlineClass::query()->forEnrollment($e)->with(['subject:id,name,name_bn', 'teacher:id,name'])
                ->where(fn ($q) => $q->where('starts_at', '>=', now()->subDays(30)))->orderBy('starts_at')->limit(100)->get()
                ->map(fn (OnlineClass $c) => [
                    'id' => $c->id, 'title' => $c->title, 'mode' => $c->mode, 'provider' => $c->provider, 'subject' => $c->subject?->displayName(), 'teacher' => $c->teacher?->name,
                    'starts_at' => $c->starts_at->toIso8601String(), 'ends_at' => $c->ends_at->toIso8601String(), 'status' => $c->status, 'phase' => $c->phase(),
                    'room' => $c->room, 'description' => $c->description,
                    // the meeting link and passcode are only handed out when the class can actually be joined
                    'join_url' => $c->joinable() ? $c->join_url : null, 'meeting_id' => $c->joinable() ? $c->meeting_id : null, 'passcode' => $c->joinable() ? $c->passcode : null,
                    'recording_url' => $c->status !== 'cancelled' ? $c->recording_url : null,
                ])->values();
        });
    }

    public function materials(Request $request, PortalService $portal): JsonResponse
    {
        return $this->perStudent($request, $portal, function (Student $s) {
            $e = $s->currentEnrollment;

            return ($e ? LearningMaterial::query()->published()->forEnrollment($e)->with('subject:id,name,name_bn')->latest('published_at')->latest('id')->get() : collect())
                ->map(fn (LearningMaterial $m) => [
                    'id' => $m->id, 'type' => $m->type, 'title' => $m->title, 'description' => $m->description, 'subject' => $m->subject?->displayName(),
                    'url' => $m->hasFile() ? null : $m->url, 'file_name' => $m->file_name, 'published_at' => $m->published_at?->toIso8601String(),
                ])->values();
        });
    }

    public function quizzes(Request $request, PortalService $portal): JsonResponse
    {
        return $this->perStudent($request, $portal, function (Student $s) {
            $e = $s->currentEnrollment;
            $quizzes = $e ? Quiz::query()->visibleToStudents()->forEnrollment($e)->with('subject:id,name,name_bn')->withCount('questions')->latest('id')->get() : collect();
            $attempts = QuizAttempt::query()->where('student_id', $s->id)->whereIn('quiz_id', $quizzes->pluck('id'))->whereNotNull('submitted_at')->get()->groupBy('quiz_id');

            return $quizzes->map(function (Quiz $q) use ($attempts) {
                $mine = $attempts->get($q->id, collect());
                $best = $mine->sortByDesc('percent')->first();

                return [
                    'id' => $q->id, 'title' => $q->title, 'subject' => $q->subject?->displayName(), 'questions' => $q->questions_count, 'time_limit_minutes' => $q->time_limit_minutes,
                    'max_attempts' => $q->max_attempts, 'attempts_used' => $mine->count(), 'status' => $q->status, 'closes_at' => $q->available_until?->toIso8601String(),
                    'pass_percent' => $q->pass_percent !== null ? $q->pass_percent + 0 : null,
                    'best' => $best ? ['score' => $best->score + 0, 'max_score' => $best->max_score + 0, 'percent' => $best->percent + 0] : null,
                ];
            })->values();
        });
    }

    public function progress(Request $request, PortalService $portal, LearningProgress $progress): JsonResponse
    {
        return $this->perStudent($request, $portal, function (Student $s) use ($progress) {
            $p = $progress->forStudent($s);

            return collect([[
                'assignments' => ['total' => $p['total'], 'handed_in' => $p['done'], 'pending' => $p['pending'], 'missing' => $p['missing'], 'late' => $p['late'], 'evaluated' => $p['evaluated'], 'average_percent' => $p['average']],
                'subjects' => $p['subjects'], 'class_attendance' => $p['classes'], 'quizzes' => $p['quizzes'],
            ]]);
        }, flat: true);
    }

    /** Run $fn for the student, or for every child of a guardian; an unlinked login gets 404. */
    protected function perStudent(Request $request, PortalService $portal, \Closure $fn, bool $flat = false): JsonResponse
    {
        $user = $request->user();
        $students = collect();
        if ($student = $portal->studentFor($user)) {
            $students->push($student);
        } elseif ($guardian = $portal->guardianFor($user)) {
            $students = $portal->children($guardian);
        }
        abort_if($students->isEmpty(), 404);

        return $this->ok($students->map(function (Student $s) use ($fn, $flat) {
            /** @var Collection $rows */
            $rows = $fn($s);

            return ['student' => ['id' => $s->id, 'name' => $s->name, 'code' => $s->student_code]] + ($flat ? $rows->first() : ['items' => $rows->values()]);
        })->values());
    }
}
