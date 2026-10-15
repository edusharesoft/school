<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Website\Post;
use App\Services\Website\SiteContent;
use Illuminate\View\View;

/** Public news & notices. The same screens serve both types; the route decides which. */
class PostController extends Controller
{
    public function news(SiteContent $site): View
    {
        return $this->index($site, 'news');
    }

    public function notices(SiteContent $site): View
    {
        return $this->index($site, 'notice');
    }

    public function showNews(SiteContent $site, string $slug): View
    {
        return $this->show($site, 'news', $slug);
    }

    public function showNotice(SiteContent $site, string $slug): View
    {
        return $this->show($site, 'notice', $slug);
    }

    protected function index(SiteContent $site, string $type): View
    {
        $institution = $site->institution();
        abort_unless($institution, 404);

        $posts = Post::query()->where('institution_id', $institution->id)->whereNull('branch_id')->ofType($type)->live()
            ->latest('published_at')->latest('id')->paginate(9);

        return view('website.posts', ['posts' => $posts, 'type' => $type, 'institution' => $institution, 'site' => $site->settings()]);
    }

    protected function show(SiteContent $site, string $type, string $slug): View
    {
        $institution = $site->institution();
        abort_unless($institution, 404);

        $post = Post::query()->where('institution_id', $institution->id)->whereNull('branch_id')->ofType($type)->live()->where('slug', $slug)->firstOrFail();

        return view('website.post', ['post' => $post, 'institution' => $institution, 'site' => $site->settings()]);
    }
}
