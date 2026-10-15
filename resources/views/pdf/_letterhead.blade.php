{{-- Letterhead of exam documents. $branch, $logo (data URI), $accent --}}
<table style="width:100%; border-bottom:0.5mm solid {{ $accent }}" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:20mm; padding-bottom:1.5mm">@if($logo)<img src="{{ $logo }}" style="height:15mm" alt="">@endif</td>
        <td class="center" style="padding-bottom:1.5mm">
            <div style="font-size:15pt; font-weight:bold; color:{{ $accent }}">{{ $branch->institution?->name }}</div>
            <div style="font-size:11pt; font-weight:bold">{{ $branch->displayName() }}</div>
            @if($branch->address)<div style="font-size:8pt; color:#475569">{{ $branch->address }}@if($branch->phone) · {{ $branch->phone }}@endif</div>@endif
        </td>
        <td style="width:20mm"></td>
    </tr>
</table>
