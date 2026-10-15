{{-- ID cards PDF. $cards = list of IdCardService::cardData rows (+ 'photo' data URI); $layout = card|sheet; $sides = both|front. --}}
@extends('pdf._doc')
@section('title', __('ID cards'))

@php
    $isSheet = $layout === 'sheet';
    $shared = compact('color', 'branch', 'logo', 'show_blood', 'show_phone');
    $backShared = ['color' => $color, 'branch' => $branch, 'signature' => $signature, 'signatory' => $signatory, 'signatory_title' => $signatory_title, 'back_text' => $back_text, 'emergency' => $emergency];
    $cellStyle = 'padding:1.5mm; text-align:center; border:0.2mm dashed #94a3b8';
@endphp

@section('body')
@if(! $isSheet)
    {{-- one card per page (card printers): front, then back --}}
    @foreach($cards as $c)
        @if(! $loop->first)<pagebreak />@endif
        @include('pdf._id-front', ['c' => $c] + $shared)
        @if($sides === 'both')
            <pagebreak />
            @include('pdf._id-back', ['c' => $c, 'verify_url' => route('idcards.verify', ['code' => $c['card']->verification_code])] + $backShared)
        @endif
    @endforeach
@else
    {{-- A4 sheet, 3 × 3 cards; the back page mirrors every row so a duplex print (flip on the long edge) lines up --}}
    @foreach(array_chunk($cards, 9) as $group)
        @php($rows = array_chunk($group, 3))
        @if(! $loop->first)<pagebreak />@endif
        <table style="width:100%" cellpadding="0" cellspacing="0">
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $c)<td style="{{ $cellStyle }}">@include('pdf._id-front', ['c' => $c] + $shared)</td>@endforeach
                    @for($i = count($row); $i < 3; $i++)<td style="{{ $cellStyle }}"></td>@endfor
                </tr>
            @endforeach
        </table>
        @if($sides === 'both')
            <pagebreak />
            <table style="width:100%" cellpadding="0" cellspacing="0">
                @foreach($rows as $row)
                    <tr>
                        @for($i = count($row); $i < 3; $i++)<td style="{{ $cellStyle }}"></td>@endfor
                        @foreach(array_reverse($row) as $c)<td style="{{ $cellStyle }}">@include('pdf._id-back', ['c' => $c, 'verify_url' => route('idcards.verify', ['code' => $c['card']->verification_code])] + $backShared)</td>@endforeach
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach
@endif
@endsection
