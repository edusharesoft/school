<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Communication\Notice;
use App\Models\Communication\NotificationMessage;
use App\Services\Communication\NoticeAudience;
use App\Services\Communication\NoticeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /api/v1/notices                     live notices of the signed-in user's audience (?category=)
 * GET  /api/v1/notices/{id}                one notice (records the read receipt)
 * GET  /api/v1/notifications               the user's in-app notifications (?unread=1)
 * POST /api/v1/notifications/{id}/read     mark one read
 */
class NoticeController extends Controller
{
    use ApiResponse;

    public function index(Request $request, NoticeService $notices): JsonResponse
    {
        return $this->ok($notices->boardFor($request->user(), $request->input('category'))->map(fn (Notice $n) => $this->notice($n, false))->values());
    }

    public function show(Request $request, Notice $notice, NoticeAudience $audience, NoticeService $notices): JsonResponse
    {
        abort_unless($notice->isLive() && $audience->matches($notice, $request->user()), 404);
        $notices->markRead($notice, $request->user());

        return $this->ok($this->notice($notice, true));
    }

    public function notifications(Request $request): JsonResponse
    {
        $page = NotificationMessage::query()->inbox($request->user())->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest('id')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok($page->getCollection()->map(fn (NotificationMessage $m) => [
            'id' => $m->id, 'event' => $m->event, 'title' => $m->subject, 'body' => $m->body, 'url' => $m->url,
            'read_at' => $m->read_at?->toIso8601String(), 'created_at' => $m->created_at?->toIso8601String(),
        ]), null, 200, ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }

    public function read(Request $request, NotificationMessage $message): JsonResponse
    {
        abort_unless($message->channel === 'in_app' && $message->user_id === $request->user()->id, 404);
        $message->forceFill(['read_at' => $message->read_at ?? now()])->save();

        return $this->ok(['id' => $message->id, 'read_at' => $message->read_at->toIso8601String()]);
    }

    protected function notice(Notice $n, bool $full): array
    {
        return array_filter([
            'id' => $n->id, 'title' => $n->title, 'category' => $n->category, 'is_emergency' => $n->is_emergency, 'is_pinned' => $n->is_pinned,
            'excerpt' => $full ? null : $n->excerpt(200), 'body' => $full ? $n->body : null,
            'attachment' => $n->attachment_path ? ['name' => $n->attachment_name, 'url' => tenant()->branch()->url('/notices/'.$n->id.'/attachment')] : null,
            'published_at' => $n->published_at?->toIso8601String(), 'expires_at' => $n->expires_at?->toIso8601String(),
        ], fn ($v) => $v !== null);
    }
}
