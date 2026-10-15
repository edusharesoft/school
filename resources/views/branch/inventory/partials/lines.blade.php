{{-- Line editor (Alpine). $lines: rows; $items: [{id,label,unit,stock?,cost?}]; $cols: [{key,label,step,min?}];
     $hidden: extra hidden keys per row; $locked: items fixed (receipt against an order); $costKey: column used for the line value. --}}
@php
    $cols = $cols ?? [['key' => 'quantity', 'label' => __('Quantity'), 'step' => '0.001']];
    $hidden = $hidden ?? [];
    $locked = $locked ?? false;
    $costKey = $costKey ?? null;
    $blank = collect($cols)->mapWithKeys(fn ($c) => [$c['key'] => ''])->merge(['inventory_item_id' => ''])->merge(collect($hidden)->mapWithKeys(fn ($k) => [$k => '']))->all();
    $itemMap = collect($items)->keyBy('id')->all();
@endphp
@error('lines')<div class="alert alert-danger small">{{ $message }}</div>@enderror
<div x-data="{ rows: {{ Illuminate\Support\Js::from(array_values($lines)) }}, items: {{ Illuminate\Support\Js::from($itemMap) }}, blank: {{ Illuminate\Support\Js::from($blank) }},
    add() { this.rows.push({ ...this.blank }) }, remove(i) { if (this.rows.length > 1) this.rows.splice(i, 1) },
    total() { return this.rows.reduce((s, r) => s + (parseFloat(r.quantity) || 0) * (parseFloat(r.{{ $costKey ?? 'quantity' }}) || 0), 0) } }">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr>
                <th>{{ __('Item') }}</th>
                @foreach($cols as $c)<th style="width:150px">{{ $c['label'] }}</th>@endforeach
                @if($costKey)<th class="text-end" style="width:120px">{{ __('Amount (৳)') }}</th>@endif
                @unless($locked)<th style="width:40px"></th>@endunless
            </tr></thead>
            <tbody>
            <template x-for="(row, i) in rows" :key="i">
                <tr>
                    <td>
                        @if($locked)
                            <span x-text="items[row.inventory_item_id]?.label"></span>
                            <input type="hidden" :name="'lines[' + i + '][inventory_item_id]'" :value="row.inventory_item_id">
                        @else
                            <select :name="'lines[' + i + '][inventory_item_id]'" x-model="row.inventory_item_id" class="form-select form-select-sm" aria-label="{{ __('Item') }}">
                                <option value="">—</option>
                                @foreach($items as $it)<option value="{{ $it['id'] }}">{{ $it['label'] }}@isset($it['stock']) ({{ rtrim(rtrim(number_format($it['stock'], 3, '.', ''), '0'), '.') }} {{ $it['unit'] }})@endisset</option>@endforeach
                            </select>
                        @endif
                        @foreach($hidden as $h)<input type="hidden" :name="'lines[' + i + '][{{ $h }}]'" :value="row.{{ $h }}">@endforeach
                    </td>
                    @foreach($cols as $c)
                        <td><div class="input-group input-group-sm">
                            <input type="number" step="{{ $c['step'] ?? '0.01' }}" @isset($c['min']) min="{{ $c['min'] }}" @endisset :name="'lines[' + i + '][{{ $c['key'] }}]'" x-model="row.{{ $c['key'] }}" class="form-control text-end" aria-label="{{ $c['label'] }}"
                                @if($c['key'] === 'quantity' && ($c['pending'] ?? false)) :max="row.max" @endif>
                            @if($c['key'] === 'quantity')<span class="input-group-text" x-text="items[row.inventory_item_id]?.unit || ''"></span>@endif
                        </div></td>
                    @endforeach
                    @if($costKey)<td class="text-end" x-text="((parseFloat(row.quantity) || 0) * (parseFloat(row.{{ $costKey }}) || 0)).toFixed(2)"></td>@endif
                    @unless($locked)<td><button type="button" class="btn btn-sm btn-outline-danger" @click="remove(i)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></td>@endunless
                </tr>
            </template>
            </tbody>
            @if($costKey)<tfoot><tr><th class="text-end" colspan="{{ count($cols) + 1 }}">{{ __('Total') }}</th><th class="text-end" x-text="total().toFixed(2)"></th><th></th></tr></tfoot>@endif
        </table>
    </div>
    @unless($locked)<button type="button" class="btn btn-sm btn-outline-secondary mt-2" @click="add()"><i class="bi bi-plus-lg me-1"></i>{{ __('Add line') }}</button>@endunless
</div>
