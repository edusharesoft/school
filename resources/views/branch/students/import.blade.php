@extends('layouts.branch')
@section('title', __('Import students'))

@section('content')
<x-page-header :title="__('Import students from CSV')" :breadcrumbs="[__('Students') => route('branch.students.index'), __('Import') => null]" />

<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('branch.students.import.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-body row g-3">
                <x-form.select name="academic_session_id" :label="__('Enroll into session')" required col="col-12"
                    :options="$sessions->pluck('name', 'id')" :value="$sessions->firstWhere('is_current', true)?->id" />
                <div class="col-12">
                    <label class="form-label" for="file">{{ __('CSV file (UTF-8)') }} <span class="text-danger">*</span></label>
                    <input id="file" type="file" name="file" accept=".csv,text/csv" required class="form-control @error('file') is-invalid @enderror">
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('Up to 2,000 rows. In Excel use "Save as → CSV UTF-8" to keep Bangla text.') }}</div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="dry_run" value="0">
                        <input class="form-check-input" type="checkbox" name="dry_run" value="1" id="dry_run" checked>
                        <label class="form-check-label" for="dry_run">{{ __('Validate only (dry run) — nothing is saved') }}</label>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button class="btn btn-brand" type="submit"><i class="bi bi-upload me-1"></i>{{ __('Upload') }}</button>
                <a href="{{ route('branch.students.import.template') }}" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>{{ __('Template') }}</a>
            </div>
        </form>
    </div>
    <div class="col-lg-7">
        @if($result = session('import_result'))
            <div class="card mb-3 border-{{ $result['ok'] ? 'success' : 'danger' }}">
                <div class="card-header bg-transparent">
                    <strong>{{ $result['ok'] ? __('Result: OK') : __('Result: errors found') }}</strong>
                    <span class="small text-body-secondary ms-2">{{ __('Rows') }}: {{ $result['total'] }} · {{ __('Imported') }}: {{ $result['imported'] }}</span>
                </div>
                @if($result['errors'])
                    <div class="table-responsive" style="max-height: 420px">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Line') }}</th><th>{{ __('Problems') }}</th></tr></thead>
                            <tbody>
                            @foreach($result['errors'] as $line => $messages)
                                <tr><td>{{ $line }}</td><td><ul class="mb-0 ps-3 small">@foreach($messages as $m)<li>{{ $m }}</li>@endforeach</ul></td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
        <div class="card">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Columns') }}</h2></div>
            <div class="card-body small">
                <p class="mb-2">{{ __('Header row is required. Required columns are marked *. Dates use YYYY-MM-DD. class_code / group_code must match codes set up under Academics.') }}</p>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($columns as $col)
                        <code class="border rounded px-1">{{ $col }}{{ in_array($col, ['name', 'gender', 'date_of_birth', 'class_code', 'section', 'guardian_name', 'guardian_phone', 'guardian_relation'], true) ? ' *' : '' }}</code>
                    @endforeach
                </div>
                <p class="mt-2 mb-0">{{ __('gender: male / female / other · guardian_relation: father, mother, brother, sister, uncle, aunt, grandparent, other') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
