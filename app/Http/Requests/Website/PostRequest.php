<?php

namespace App\Http\Requests\Website;

use App\Models\Website\Post;
use App\Services\Website\SiteContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');
        // Content of another site (another branch, or the institution site from a branch host) does not exist for this user.
        abort_if($post instanceof Post && $post->branch_id !== tenant()->branchId(), 404);

        return $post instanceof Post ? $this->user()->can('update', $post) : $this->user()->can('create', Post::class);
    }

    protected function prepareForValidation(): void
    {
        $slug = Str::slug((string) ($this->input('slug') ?: $this->input('title')));
        $this->merge(['slug' => $slug !== '' ? $slug : 'post-'.Str::lower(Str::random(6))]);
    }

    public function rules(): array
    {
        /** @var Post|null $post */
        $post = $this->route('post');
        $institutionId = $post?->institution_id ?? app(SiteContent::class)->forAdmin($this->user())?->id;

        return [
            'type' => [$post ? 'prohibited' : 'required', Rule::in(Post::TYPES)],
            'title' => ['required', 'string', 'max:191'],
            'title_bn' => ['nullable', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('website_posts', 'slug')->where('institution_id', $institutionId)->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string', 'max:100000'],
            'body_bn' => ['nullable', 'string', 'max:100000'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'published_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['published', 'draft'])],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'remove_image' => ['nullable', 'boolean'],
            'remove_attachment' => ['nullable', 'boolean'],
        ];
    }
}
