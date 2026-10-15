{{-- Wording of a staff certificate. Needs $certificate; shared by the PDF (and any print view). --}}
@php
    $d = $certificate->data;
    $female = ($d['gender'] ?? 'male') === 'female';
    $pronoun = __($female ? 'she' : 'he');
    $possessive = __($female ? 'her' : 'his');
    $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y') : '—';
    $post = ($d['designation'] ?? null) ?: __('a member of staff');
@endphp
@if($certificate->type === 'service')
    {{ __('This is to certify that :name (Employee ID :code) is working in this institution as :post:department since :from (:employment). :Possessive conduct is :conduct.', [
        'name' => $d['name'], 'code' => $d['employee_code'], 'post' => $post, 'department' => ! empty($d['department']) ? ', '.__('department of :d', ['d' => $d['department']]) : '',
        'from' => $fmt($d['joining_date'] ?? null), 'employment' => __(ucfirst(str_replace('_', ' ', $d['employment_type'] ?? 'permanent'))), 'Possessive' => ucfirst($possessive), 'conduct' => $d['conduct'] ?? __('Good'),
    ]) }}
@else
    {{ __('This is to certify that :name (Employee ID :code) worked in this institution as :post:department from :from to :to. During this period :possessive conduct was :conduct.', [
        'name' => $d['name'], 'code' => $d['employee_code'], 'post' => $post, 'department' => ! empty($d['department']) ? ', '.__('department of :d', ['d' => $d['department']]) : '',
        'from' => $fmt($d['joining_date'] ?? null), 'to' => $fmt($d['leaving_date'] ?? null), 'possessive' => $possessive, 'conduct' => $d['conduct'] ?? __('Good'),
    ]) }}
@endif
@if(! empty($d['purpose']))<div class="mt-2">{{ __('This certificate is issued on request for: :purpose.', ['purpose' => $d['purpose']]) }}</div>@endif
@if(! empty($d['remarks']))<div class="mt-2">{{ __('Remarks') }}: {{ $d['remarks'] }}</div>@endif
<div class="mt-2">{{ __('I wish :pronoun every success in life.', ['pronoun' => __($female ? 'her' : 'him')]) }}</div>
