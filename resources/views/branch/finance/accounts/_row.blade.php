<tr @class(['table-light fw-semibold' => $account->is_group, 'text-body-tertiary' => ! $account->is_active])>
    <td><code>{{ $account->code }}</code></td>
    <td style="padding-left: {{ 8 + $depth * 22 }}px">
        {{ $account->name }}
        @if($account->system_key)<i class="bi bi-key text-body-tertiary ms-1" title="{{ $account->system_key }}"></i>@endif
        @if($account->kind)<span class="badge text-bg-light border ms-1">{{ __(ucfirst($account->kind)) }}</span>@endif
        @if($account->account_number)<span class="small text-body-secondary ms-1">{{ $account->bank_name }} {{ $account->account_number }}</span>@endif
    </td>
    <td class="small">{{ __(ucfirst($account->type)) }}</td>
    <td class="text-end">{{ number_format($balance($account), 2) }}</td>
    <td class="text-end">@if(auth()->user()->can('accounting.post'))<a href="{{ route('branch.finance.accounts.index', ['edit' => $account->id]) }}" class="btn btn-sm btn-link p-0" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>@endif</td>
</tr>
@foreach($accounts->where('parent_id', $account->id)->sortBy('code') as $child)
    @include('branch.finance.accounts._row', ['account' => $child, 'depth' => $depth + 1])
@endforeach
