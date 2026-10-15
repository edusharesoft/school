{{-- Front of one ID card (54 × 85.6 mm). Needs $c (IdCardService::cardData + photo/logo data URIs), $color, $branch. --}}
@php($w = config('pdf.card.width'))
@php($h = config('pdf.card.height'))
<table style="width:{{ $w }}mm; height:{{ $h }}mm; border-collapse:separate; border:0.2mm solid #cbd5e1; background:#ffffff" cellpadding="0" cellspacing="0">
    <tr>
        <td style="height:13.4mm; background-color:{{ $color }}; color:#ffffff; padding:1.6mm 2mm 1mm 2mm;">
            <table style="width:100%" cellpadding="0" cellspacing="0"><tr>
                @if(! empty($logo))<td style="width:10mm; vertical-align:middle"><img src="{{ $logo }}" style="width:9mm; height:9mm" alt=""></td>@endif
                <td style="vertical-align:middle; color:#ffffff">
                    <div style="font-size:7pt; font-weight:bold; color:#ffffff">{{ \Illuminate\Support\Str::limit($branch->institution?->name ?? $branch->name, 60, '') }}</div>
                    <div style="font-size:5.5pt; color:#ffffff">{{ \Illuminate\Support\Str::limit($branch->displayName(), 50, '') }}</div>
                </td>
            </tr></table>
        </td>
    </tr>
    <tr>
        <td style="height:28.5mm; text-align:center; padding-top:2.5mm;">
            @if(! empty($c['photo']))
                <img src="{{ $c['photo'] }}" style="width:22mm; height:27mm; border:0.3mm solid {{ $color }}" alt="">
            @else
                <table style="width:22mm; height:27mm; margin:0 auto; border-collapse:separate; border:0.3mm solid {{ $color }}" cellpadding="0" cellspacing="0"><tr><td style="height:27mm; text-align:center; vertical-align:middle; background-color:#e2e8f0; color:#64748b; font-size:16pt">{{ mb_strtoupper(mb_substr($c['name'], 0, 1)) }}</td></tr></table>
            @endif
        </td>
    </tr>
    <tr>
        <td style="height:13mm; text-align:center; padding:0 1.5mm">
            <div style="font-size:9pt; font-weight:bold; color:#0f172a">{{ \Illuminate\Support\Str::limit($c['name'], 28, '') }}</div>
            @if(! empty($c['name_bn']))<div style="font-size:7.5pt; color:#334155">{{ \Illuminate\Support\Str::limit($c['name_bn'], 28, '') }}</div>@endif
            <div style="font-size:6.5pt; font-weight:bold; color:{{ $color }}">{{ $c['line1'] }}</div>
        </td>
    </tr>
    <tr>
        <td style="height:15mm; padding:0 3mm">
            <table style="width:100%; font-size:6pt; text-align:left" cellpadding="0" cellspacing="0">
                <tr><td style="width:12mm; color:#64748b">{{ $c['kind'] === 'student' ? __('ID') : __('Staff ID') }}</td><td style="font-weight:bold">{{ $c['code'] }}</td></tr>
                @if(! empty($c['line2']))<tr><td colspan="2">{{ $c['line2'] }}@if(! empty($c['session'])) · {{ $c['session'] }}@endif</td></tr>@endif
                @if(! empty($c['blood_group']) && ! empty($show_blood))<tr><td style="color:#64748b">{{ __('Blood') }}</td><td style="font-weight:bold; color:#b91c1c">{{ $c['blood_group'] }}</td></tr>@endif
                @if(! empty($c['phone']) && ! empty($show_phone))<tr><td style="color:#64748b">{{ $c['phone_label'] }}</td><td>{{ $c['phone'] }}</td></tr>@endif
            </table>
        </td>
    </tr>
    <tr>
        <td style="height:8.6mm; text-align:center; background-color:#f1f5f9; padding-top:1mm">
            <barcode code="{{ $c['code'] }}" type="C128B" size="0.5" height="0.5" />
        </td>
    </tr>
</table>
