@extends('layouts.branch')
@section('title', __('New invoice'))

@section('content')
<x-page-header :title="__('New invoice')" :breadcrumbs="[__('Invoices') => route('branch.finance.invoices.index'), $student->name => route('branch.finance.students.show', $student), __('New invoice') => null]" />

<form method="POST" action="{{ route('branch.finance.invoices.store') }}" class="card" style="max-width: 900px">
    @csrf
    <input type="hidden" name="student_id" value="{{ $student->id }}">
    <div class="card-body row g-3">
        <div class="col-12 small">{{ __('Student') }}: <strong>{{ $student->name }}</strong> <code>{{ $student->student_code }}</code> · {{ $student->currentEnrollment?->schoolClass?->name }}</div>
        <x-form.input name="title" :label="__('Title')" :value="old('title', __('Admission / other fees'))" required col="col-md-6" maxlength="150" />
        <x-form.input name="issue_date" type="date" :label="__('Issue date')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.input name="due_date" type="date" :label="__('Due date')" :value="now()->addDays(7)->toDateString()" required col="col-md-3" />
        <div class="col-12">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>{{ __('Fee head') }}</th><th>{{ __('Description (optional)') }}</th><th style="width:140px">{{ __('Amount (৳)') }}</th><th style="width:140px">{{ __('Discount (৳)') }}</th></tr></thead>
                <tbody>
                @for($i = 0; $i < 6; $i++)
                    <tr>
                        <td><select name="items[{{ $i }}][fee_head_id]" class="form-select form-select-sm" aria-label="{{ __('Fee head') }}">
                            <option value="">—</option>
                            @foreach($heads as $h)<option value="{{ $h->id }}" @selected((int) old("items.$i.fee_head_id") === $h->id)>{{ $h->name }}</option>@endforeach
                        </select></td>
                        <td><input name="items[{{ $i }}][description]" value="{{ old("items.$i.description") }}" class="form-control form-control-sm" maxlength="150" aria-label="{{ __('Description') }}"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][amount]" value="{{ old("items.$i.amount") }}" class="form-control form-control-sm text-end" aria-label="{{ __('Amount') }}"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][discount]" value="{{ old("items.$i.discount") }}" class="form-control form-control-sm text-end" aria-label="{{ __('Discount') }}"></td>
                    </tr>
                @endfor
                </tbody>
            </table>
            @error('items')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <x-form.input name="notes" :label="__('Note printed on the invoice')" col="col-12" maxlength="255" />
    </div>
    <x-form.actions :cancel="route('branch.finance.students.show', $student)">{{ __('Create invoice') }}</x-form.actions>
</form>
@endsection
