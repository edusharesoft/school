@extends('layouts.branch')
@section('title', __('Admission test marks'))

@section('content')
<x-page-header :title="__('Admission test marks').': '.$campaign->title" :breadcrumbs="[__('Admissions') => route('branch.admissions.campaigns.index'), __('Test marks') => null]" />

<form method="POST" action="{{ route('branch.admissions.merit.marks.store', $campaign) }}" class="card">
    @csrf
    <div class="card-body border-bottom small text-body-secondary">{{ __('Total marks') }}: <strong>{{ bn_digits($campaign->test_total_marks) }}</strong> · {{ __('Pass marks') }}: <strong>{{ $campaign->test_pass_marks === null ? '—' : bn_digits($campaign->test_pass_marks) }}</strong></div>
    @if($applications->isEmpty())
        <x-empty-state icon="inbox" :title="__('No applicants waiting for a result')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Application no.') }}</th><th>{{ __('Applicant') }}</th><th style="width:140px">{{ __('Marks') }}</th><th>{{ __('Absent') }}</th></tr></thead>
                <tbody>
                @foreach($applications as $a)
                    <tr>
                        <td><code>{{ $a->application_no }}</code></td>
                        <td>{{ $a->name }}</td>
                        <td><input type="number" step="0.01" min="0" max="{{ $campaign->test_total_marks }}" name="rows[{{ $a->id }}][marks]" value="{{ old('rows.'.$a->id.'.marks', $a->test_marks) }}" class="form-control form-control-sm" aria-label="{{ __('Marks') }}"></td>
                        <td><input type="hidden" name="rows[{{ $a->id }}][absent]" value="0"><input class="form-check-input" type="checkbox" name="rows[{{ $a->id }}][absent]" value="1" @checked($a->test_absent) aria-label="{{ __('Absent') }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent d-flex gap-2">
            <button class="btn btn-brand">{{ __('Save marks') }}</button>
            <a href="{{ route('branch.admissions.merit.show', $campaign) }}" class="btn btn-outline-secondary">{{ __('Merit list') }}</a>
        </div>
    @endif
</form>
@endsection
