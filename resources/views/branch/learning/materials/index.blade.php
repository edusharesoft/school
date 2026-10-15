@extends($layout)
@section('title', __('Class materials'))

@section('content')
<x-page-header :title="__('Class materials')" :breadcrumbs="[__('Learning') => null, __('Class materials') => null]">
    @can('materials.create')
        <x-slot:actions><a href="{{ route($routePrefix.'learning.materials.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add material') }}</a></x-slot:actions>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}">
                <option value="">{{ __('All types') }}</option>
                @foreach(App\Models\Learning\LearningMaterial::TYPES as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ __(Illuminate\Support\Str::headline($t)) }}</option>@endforeach
            </select></div>
            <div class="col-md-3"><select name="class_id" class="form-select form-select-sm" aria-label="{{ __('Class') }}">
                <option value="">{{ __('All classes') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}" @selected((int) request('class_id') === $id)>{{ $name }}</option>@endforeach
            </select></div>
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search title') }}" aria-label="{{ __('Search title') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($materials->isEmpty())
        <x-empty-state icon="folder2-open" :title="__('No materials yet')" :message="__('Share notes, worksheets, video lessons or links with your classes.')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Class') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($materials as $m)
                <tr>
                    <td class="fw-semibold">{{ $m->title }}<div class="text-body-secondary fw-normal">{{ $m->creator?->name }} · {{ $m->created_at->format('d M Y') }}</div></td>
                    <td>{{ __(Illuminate\Support\Str::headline($m->type)) }}</td>
                    <td>{{ $m->schoolClass?->name }}@if($m->section) — {{ $m->section->name }}@endif</td>
                    <td>{{ $m->subject?->displayName() ?? '—' }}</td>
                    <td><span class="badge text-bg-{{ $m->is_published ? 'success' : 'secondary' }}">{{ $m->is_published ? __('Shared') : __('Hidden') }}</span></td>
                    <td>
                        <x-row-actions :edit="route($routePrefix.'learning.materials.edit', $m)" :destroy="route($routePrefix.'learning.materials.destroy', $m)" :confirm="__('Delete this material?')"
                            :can-edit="auth()->user()->can('materials.update')" :can-delete="auth()->user()->can('materials.delete')">
                            @if($m->hasFile())<a href="{{ route($routePrefix.'learning.materials.file', $m) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Download') }}"><i class="bi bi-download"></i></a>
                            @elseif($m->url)<a href="{{ $m->url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Open') }}"><i class="bi bi-box-arrow-up-right"></i></a>@endif
                        </x-row-actions>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer bg-transparent">{{ $materials->links() }}</div>
    @endif
</div>
@endsection
