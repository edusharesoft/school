<?php

namespace App\Http\Controllers\Central\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\PostRequest;
use App\Models\Website\Post;
use App\Services\Website\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** News / blog articles and public notices of the website. */
class PostController extends Controller
{
    /** Layout and route-name prefix of the screens; the branch-site controller overrides them. */
    protected string $layout = 'layouts.central';

    protected string $routePrefix = 'central.website.';

    public function __construct(protected SiteContent $site) {}

    /** null = the institution-wide site, otherwise the branch whose site is being edited. */
    protected function siteBranchId(): ?int
    {
        return null;
    }

    /** A post of another site is invisible here. */
    protected function own(Post $post): Post
    {
        abort_unless($post->branch_id === $this->siteBranchId(), 404);

        return $post;
    }

    protected function ui(array $data = []): array
    {
        return $data + ['layout' => $this->layout, 'routePrefix' => $this->routePrefix];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Post::class);
        $institution = $this->site->forAdmin($request->user());

        $posts = Post::query()->where('institution_id', $institution?->id)->ofSite($this->siteBranchId())
            ->when(in_array($request->input('type'), Post::TYPES, true), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where('title', 'like', "%{$t}%"))
            ->latest('published_at')->latest('id')->paginate(20)->withQueryString();

        return view('central.website.posts.index', $this->ui(compact('posts')));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);

        return view('central.website.posts.form', $this->ui(['post' => new Post([
            'type' => $request->input('type') === 'notice' ? 'notice' : 'news',
            'status' => 'published',
            'published_at' => now(),
            'author_name' => $request->user()->name,
        ])]));
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $institution = $this->site->forAdmin($request->user());
        abort_unless($institution, 404);

        $post = new Post($request->safe()->except(['image', 'attachment', 'remove_image', 'remove_attachment']));
        $post->institution_id = $institution->id;
        $post->branch_id = $this->siteBranchId();
        $post->created_by = $request->user()->id;
        $post->published_at ??= now();
        $this->storeFiles($request, $post);
        $post->save();

        return redirect()->route($this->routePrefix.'posts.index')->with('success', __('Post saved.'));
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $this->own($post));

        return view('central.website.posts.form', $this->ui(compact('post')));
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $this->own($post);
        $post->fill($request->safe()->except(['image', 'attachment', 'remove_image', 'remove_attachment', 'type']));
        $this->storeFiles($request, $post);
        $post->save();

        return redirect()->route($this->routePrefix.'posts.index')->with('success', __('Post saved.'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $this->own($post));

        $post->forceFill(['slug' => $post->slug.'-del-'.$post->id])->save();
        $post->delete();

        return redirect()->route($this->routePrefix.'posts.index')->with('success', __('Post deleted.'));
    }

    protected function storeFiles(PostRequest $request, Post $post): void
    {
        foreach (['image' => 'image_path', 'attachment' => 'attachment_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $this->forget($post->{$column});
                $post->{$column} = $request->file($input)->store('website/posts', 'public');
            } elseif ($request->boolean('remove_'.$input)) {
                $this->forget($post->{$column});
                $post->{$column} = null;
            }
        }
    }

    protected function forget(?string $path): void
    {
        if ($path && str_starts_with($path, 'website/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
