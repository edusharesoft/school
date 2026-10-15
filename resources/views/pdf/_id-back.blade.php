{{-- Back of one ID card (54 × 85.6 mm): rules, emergency phone, QR code for verification, validity and the signature. --}}
@php($w = config('pdf.card.width'))
@php($h = config('pdf.card.height'))
@php($card = $c['card'])
<table style="width:{{ $w }}mm; height:{{ $h }}mm; border-collapse:separate; border:0.2mm solid #cbd5e1; background:#ffffff" cellpadding="0" cellspacing="0">
    <tr>
        <td style="height:8mm; background-color:{{ $color }}; color:#ffffff; text-align:center; vertical-align:middle; font-size:7pt; font-weight:bold">
            {{ $c['kind'] === 'student' ? __('STUDENT ID CARD') : __('STAFF ID CARD') }}
        </td>
    </tr>
    <tr>
        <td style="height:23mm; padding:2mm 3mm 0 3mm; font-size:5.8pt; color:#334155">
            {!! nl2br(e((string) $back_text)) !!}
            @if($branch->address)<div style="margin-top:1.2mm"><b>{{ $branch->displayName() }}</b><br>{{ \Illuminate\Support\Str::limit($branch->address, 90, '…') }}</div>@endif
            @if(! empty($emergency))<div style="margin-top:1mm">{{ __('Emergency') }}: <b>{{ $emergency }}</b></div>@endif
        </td>
    </tr>
    <tr>
        <td style="height:21mm; text-align:center; padding-top:1mm">
            @if(app(\App\Services\Documents\PdfService::class)->qrAvailable())<barcode code="{{ $verify_url }}" type="QR" error="M" size="0.62" disableborder="1" />@else<div style="font-size:5.5pt">{{ __('Verify') }}: {{ preg_replace('#^https?://#', '', $verify_url) }}</div>@endif
            <div style="font-size:5pt; color:#64748b">{{ $card->card_no }}</div>
        </td>
    </tr>
    <tr>
        <td style="height:18mm; text-align:center; padding-top:1mm">
            <div style="font-size:6.5pt; color:#0f172a">{{ __('Valid until') }}: <b>{{ $card->valid_until->format('d M Y') }}</b></div>
            @if(! empty($signature))<img src="{{ $signature }}" style="height:7mm" alt="">@else<div style="height:7mm"></div>@endif
            <div style="font-size:5.5pt; border-top:0.2mm solid #94a3b8; margin:0 8mm; padding-top:0.5mm">{{ $signatory }}</div>
            <div style="font-size:5pt; color:#64748b">{{ __($signatory_title) }}</div>
        </td>
    </tr>
    <tr>
        <td style="height:10.6mm; background-color:{{ $color }}; color:#ffffff; text-align:center; vertical-align:middle; font-size:5.5pt">
            {{ $branch->phone }}@if($branch->email) · {{ $branch->email }}@endif
        </td>
    </tr>
</table>
