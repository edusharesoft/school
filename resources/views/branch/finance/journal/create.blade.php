@extends('layouts.branch')
@section('title', __('New voucher'))

@section('content')
<x-page-header :title="__('New voucher')" :breadcrumbs="[__('Day book') => route('branch.finance.journal.index'), __('New voucher') => null]" />

<form method="POST" action="{{ route('branch.finance.journal.store') }}" class="card" id="voucher-form" style="max-width: 1000px">
    @csrf
    <div class="card-body row g-3">
        <x-form.select name="type" :label="__('Voucher type')" required col="col-md-3" :value="'journal'"
            :options="['journal' => __('Journal'), 'contra' => __('Contra (cash ↔ bank)'), 'receipt' => __('Receipt'), 'payment' => __('Payment')]" />
        <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.input name="narration" :label="__('Narration')" required col="col-md-6" maxlength="255" :placeholder="__('e.g. Opening balance / cash deposited to bank')" />
        @error('lines')<div class="col-12"><div class="alert alert-danger small mb-0">{{ $message }}</div></div>@enderror
        <div class="col-12">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>{{ __('Account') }}</th><th>{{ __('Line note') }}</th><th style="width:150px">{{ __('Debit (৳)') }}</th><th style="width:150px">{{ __('Credit (৳)') }}</th></tr></thead>
                <tbody>
                @for($i = 0; $i < 8; $i++)
                    <tr>
                        <td><select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm" aria-label="{{ __('Account') }}"><option value="">—</option>
                            @foreach($accounts as $id => $name)<option value="{{ $id }}" @selected((int) old("lines.$i.account_id") === $id)>{{ $name }}</option>@endforeach</select></td>
                        <td><input name="lines[{{ $i }}][description]" value="{{ old("lines.$i.description") }}" class="form-control form-control-sm" maxlength="255" aria-label="{{ __('Line note') }}"></td>
                        <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ old("lines.$i.debit") }}" class="form-control form-control-sm text-end" data-dr aria-label="{{ __('Debit') }}"></td>
                        <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ old("lines.$i.credit") }}" class="form-control form-control-sm text-end" data-cr aria-label="{{ __('Credit') }}"></td>
                    </tr>
                @endfor
                </tbody>
                <tfoot><tr><th colspan="2" class="text-end">{{ __('Total') }}</th><th class="text-end" id="sum-dr">0.00</th><th class="text-end" id="sum-cr">0.00</th></tr></tfoot>
            </table>
            <div class="small mt-1" id="balance-note"></div>
        </div>
    </div>
    <x-form.actions :cancel="route('branch.finance.journal.index')">{{ __('Post voucher') }}</x-form.actions>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const f = document.getElementById('voucher-form');
    const sum = (sel) => [...f.querySelectorAll(sel)].reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
    const update = () => {
        const dr = sum('[data-dr]'), cr = sum('[data-cr]');
        document.getElementById('sum-dr').textContent = dr.toFixed(2);
        document.getElementById('sum-cr').textContent = cr.toFixed(2);
        const note = document.getElementById('balance-note');
        note.className = 'small mt-1 ' + (Math.abs(dr - cr) < 0.005 && dr > 0 ? 'text-success' : 'text-danger');
        note.textContent = Math.abs(dr - cr) < 0.005 && dr > 0 ? @json(__('Balanced')) : @json(__('Debit and credit must be equal'));
    };
    f.addEventListener('input', update);
    update();
});
</script>
@endpush
