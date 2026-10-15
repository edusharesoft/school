<?php

namespace App\Http\Requests\Website;

use App\Models\Website\Page;
use App\Services\Website\SiteContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');
        // Content of another site (another branch, or the institution site from a branch host) does not exist for this user.
        abort_if($page instanceof Page && $page->branch_id !== tenant()->branchId(), 404);

        return $page instanceof Page ? $this->user()->can('update', $page) : $this->user()->can('create', Page::class);
    }

    protected function prepareForValidation(): void
    {
        $slug = Str::slug((string) ($this->input('slug') ?: $this->input('title')));
        $this->merge(['slug' => $slug !== '' ? $slug : 'page-'.Str::lower(Str::random(6))]);
    }

    public function rules(): array
    {
        /** @var Page|null $page */
        $page = $this->route('page');
        $institutionId = $page?->institution_id ?? app(SiteContent::class)->forAdmin($this->user())?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'title_bn' => ['nullable', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('website_pages', 'slug')->where('institution_id', $institutionId)->ignore($page?->id)],
            'body' => ['nullable', 'string', 'max:100000'],
            'body_bn' => ['nullable', 'string', 'max:100000'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'placement' => ['required', Rule::in(Page::PLACEMENTS)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', Rule::in(['published', 'draft'])],
        ];
    }
}
