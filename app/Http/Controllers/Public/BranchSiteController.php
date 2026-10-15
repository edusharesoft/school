<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Website\Page;
use App\Models\Website\Post;
use App\Models\Website\Subscriber;
use App\Services\Website\BranchSite;
use App\Services\Website\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The public website of one branch, served on the branch's own address. Public data only: the branch's own
 * content (settings, pages, news, notices) plus the directory of the institution's other branches.
 */
class BranchSiteController extends Controller
{
    public function __construct(protected BranchSite $site, protected SiteContent $central) {}

    public function home(): View
    {
        $branch = $this->branch();

        return view('branch-site.home', $this->data($branch) + [
            'branches' => $this->branches($branch),
            'news' => $this->site->latestNews($branch, 3),
        ]);
    }

    public function page(string $slug): View
    {
        $branch = $this->branch();
        $page = Page::query()->where('branch_id', $branch->id)->published()->where('slug', $slug)->firstOrFail();

        return view('website.page', $this->data($branch) + ['page' => $page]);
    }

    public function news(): View
    {
        return $this->index('news');
    }

    public function notices(): View
    {
        return $this->index('notice');
    }

    public function showNews(string $slug): View
    {
        return $this->show('news', $slug);
    }

    public function showNotice(string $slug): View
    {
        return $this->show('notice', $slug);
    }

    /** Full text of one of the (up to two) messages shown on the home page. */
    public function message(int $n): View
    {
        $branch = $this->branch();
        $items = $this->site->settings($branch)['messages']['items'] ?? [];
        abort_unless(isset($items[$n - 1]), 404);

        return view('branch-site.message', $this->data($branch) + ['message' => $items[$n - 1]]);
    }

    public function newsletter(Request $request): RedirectResponse
    {
        $branch = $this->branch();

        // Bots fill the hidden "website" field; humans never see it. Pretend success.
        if ($request->filled('website')) {
            return back()->with('success', __('Thank you! You are subscribed.'));
        }

        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:191']]);
        $email = Str::lower(trim($data['email']));

        $subscriber = Subscriber::query()->where('institution_id', $branch->institution_id)->where('email', $email)->first();
        if ($subscriber) {
            $subscriber->forceFill(['unsubscribed_at' => null, 'locale' => app()->getLocale()])->save();
        } else {
            $subscriber = new Subscriber(['email' => $email, 'locale' => app()->getLocale(), 'ip' => $request->ip()]);
            $subscriber->institution_id = $branch->institution_id;
            $subscriber->unsubscribe_token = Str::random(48);
            $subscriber->save();
        }

        return redirect()->to(route('branch.home').'#newsletter')->with('success', __('Thank you! You are subscribed.'));
    }

    // ------------------------------------------------------------------ helpers

    protected function index(string $type): View
    {
        $branch = $this->branch();
        $posts = Post::query()->where('branch_id', $branch->id)->ofType($type)->live()->latest('published_at')->latest('id')->paginate(9);

        return view('website.posts', $this->data($branch) + ['posts' => $posts, 'type' => $type]);
    }

    protected function show(string $type, string $slug): View
    {
        $branch = $this->branch();
        $post = Post::query()->where('branch_id', $branch->id)->ofType($type)->live()->where('slug', $slug)->firstOrFail();

        return view('website.post', $this->data($branch) + ['post' => $post]);
    }

    protected function branch(): Branch
    {
        $branch = tenant()->branch();
        abort_unless($branch, 404);

        return $branch;
    }

    protected function data(Branch $branch): array
    {
        return [
            'branch' => $branch,
            'site' => $this->site->settings($branch),
            'nav' => $this->site->navigation($branch),
            'siteLayout' => 'layouts.branch-site',
        ];
    }

    /** The institution's active branches (this one included) for the photo grid and the map. */
    protected function branches(Branch $branch)
    {
        return $this->central->branches()->where('institution_id', $branch->institution_id)->values();
    }
}
