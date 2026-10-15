@extends('layouts.branch')
@section('title', __('Subjects of :class', ['class' => $class->name]))

@section('content')
<x-page-header :title="__('Subjects of :class', ['class' => $class->displayName()])"
    :breadcrumbs="[__('Classes') => route('branch.academics.classes.index'), $class->name => null, __('Subjects') => null]" />

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            @if($rows->isEmpty())
                <x-empty-state icon="book" :title="__('No subjects assigned yet')" />
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Group') }}</th><th>{{ __('Category') }}</th><th>{{ __('Full') }}</th><th>{{ __('Pass') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td><div class="fw-semibold">{{ $row->subject?->displayName() }}</div><code class="small">{{ $row->subject?->code }}</code></td>
                                <td>{{ $row->studyGroup?->name ?? __('All') }}</td>
                                <td><span class="badge text-bg-light border">{{ __(ucfirst($row->category)) }}</span></td>
                                <td>{{ $row->full_marks }}</td>
                                <td>{{ $row->pass_marks }}</td>
                                <td><x-row-actions :destroy="route('branch.academics.classes.subjects.destroy', [$class, $row])" :confirm="__('Remove this subject from the class?')"
                                    :can-delete="auth()->user()->can('update', $class)" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @can('update', $class)
        <div class="col-lg-4">
            <form method="POST" action="{{ route('branch.academics.classes.subjects.store', $class) }}" class="card">
                @csrf
                <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Add subject') }}</h2></div>
                <div class="card-body row g-2">
                    <x-form.select name="subject_id" :label="__('Subject')" required col="col-12" :placeholder="__('Choose…')"
                        :options="$subjects->mapWithKeys(fn ($s) => [$s->id => $s->code.' — '.$s->name])" />
                    @if($class->has_groups)
                        <x-form.select name="study_group_id" :label="__('Group')" col="col-12" :options="$groups" :placeholder="__('All groups')" />
                    @endif
                    <x-form.select name="category" :label="__('Category')" col="col-12" value="compulsory"
                        :options="collect(App\Models\Academic\ClassSubject::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __(ucfirst($c))])" />
                    <x-form.input name="full_marks" type="number" :label="__('Full marks')" value="100" col="col-6" min="1" required />
                    <x-form.input name="pass_marks" type="number" :label="__('Pass marks')" value="33" col="col-6" min="0" required />
                    <x-form.input name="sort_order" type="number" :label="__('Order')" :value="$rows->count() + 1" col="col-6" min="0" />
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-brand w-100">{{ __('Add') }}</button></div>
            </form>
        </div>
    @endcan
</div>
@endsection
