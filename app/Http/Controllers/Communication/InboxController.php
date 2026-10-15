<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Communication\NotificationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** In-app notifications of the signed-in user (every portal). */
class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('communication.inbox', [
            'messages' => NotificationMessage::query()->inbox($user)
                ->when($request->input('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))
                ->latest('id')->paginate(30)->withQueryString(),
            'unread' => NotificationMessage::query()->inbox($user)->whereNull('read_at')->count(),
            'layout' => $user->portal() === 'admin' ? 'layouts.branch' : 'layouts.portal',
        ]);
    }

    /** Bell badge + desktop notifications: unread count and the newest few (polled every minute). */
    public function unread(Request $request): JsonResponse
    {
        $query = NotificationMessage::query()->inbox($request->user())->whereNull('read_at');

        return response()->json([
            'count' => (clone $query)->count(),
            'latest' => (clone $query)->latest('id')->limit(5)->get(['id', 'subject', 'body', 'url', 'created_at'])->map(fn ($m) => [
                'id' => $m->id, 'title' => $m->subject ?: __('Notification'), 'body' => \Illuminate\Support\Str::limit($m->body, 140),
                'url' => route('inbox.open', $m), 'at' => $m->created_at?->diffForHumans(),
            ]),
        ]);
    }

    /** Mark read and follow the message's link (or show it in the inbox). */
    public function open(Request $request, NotificationMessage $message): RedirectResponse
    {
        abort_unless($message->channel === 'in_app' && $message->user_id === $request->user()->id, 404);
        if (! $message->read_at) {
            $message->forceFill(['read_at' => now()])->save();
        }
        $url = $message->url;
        $host = parse_url((string) $url, PHP_URL_HOST);

        // Only follow links to this branch's own host.
        return $url && (! $host || $host === $request->getHost()) ? redirect()->to($url) : redirect()->route('inbox.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        NotificationMessage::query()->inbox($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', __('All notifications marked as read.'));
    }
}
