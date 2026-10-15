@extends('layouts.portal')
@section('title', __('Results'))

@section('content')
<x-page-header :title="__('Results').' — '.$student->name" :breadcrumbs="[__('Home') => route(rtrim($routePrefix, '.')), __('Results') => null]" />
<div class="card">
    @if($results->isEmpty())
        <x-empty-state icon="clipboard-data" :title="__('No published results yet.')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Exam') }}</th><th>{{ __('Class') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-center">{{ __('GPA') }}</th><th class="text-center">{{ __('Grade') }}</th><th class="text-center">{{ __('Merit') }}</th><th>{{ __('Result') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($results as $r)
                    <tr>
                        <td class="fw-semibold">{{ $r->examination?->name }}<div class="small text-body-secondary">{{ $r->examination?->published_at?->format('d M Y') }}</div></td>
                        <td>{{ $r->schoolClass?->name }}</td>
                        <td class="text-end">{{ (float) $r->total_marks }} / {{ (float) $r->full_marks }}</td>
                        <td class="text-center fw-semibold">{{ $r->gpaLabel() }}</td>
                        <td class="text-center">{{ $r->grade ?? '—' }}</td>
                        <td class="text-center">{{ $r->class_position ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $r->tone() }}">{{ __(ucfirst($r->status)) }}</span></td>
                        <td class="text-end text-nowrap"><a href="{{ route($routePrefix.'results.show', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">{{ __('Mark sheet') }}</a>
                            <a href="{{ route($routePrefix.'results.pdf', $r) }}" class="btn btn-sm btn-outline-danger" title="{{ __('Download as PDF') }}" aria-label="{{ __('Download as PDF') }}"><i class="fa-solid fa-file-pdf"></i> PDF</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
