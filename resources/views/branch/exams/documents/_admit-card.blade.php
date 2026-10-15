{{-- One admit card. Needs $card (ExamDocumentService::admitCards row), $section, $exam, $branch; optional $photo URL. --}}
@php($e = $card['enrollment'])
<div class="admit">
    @unless($card['eligible'])<div class="stamp" style="font-size:44px;top:35%">{{ __('NOT ELIGIBLE') }}</div>@endunless
    @include('branch.exams.documents._letterhead')
    <div class="doc-title" style="margin:4px 0 8px">{{ __('Admit card') }} — {{ $exam->name }}</div>
    <div class="d-flex gap-3">
        <div class="flex-grow-1">
            <table class="noborder" style="font-size:13px">
                <tr><td style="width:28%">{{ __('Name') }}</td><td><strong>{{ $e->student?->name }}</strong></td></tr>
                <tr><td>{{ __('Student ID') }}</td><td>{{ $e->student?->student_code }}</td></tr>
                <tr><td>{{ __('Class / Section / Roll') }}</td><td>{{ $section->schoolClass?->name }} / {{ $section->name }} / <strong>{{ $e->roll_no }}</strong>@if($e->studyGroup) · {{ $e->studyGroup->name }}@endif</td></tr>
                <tr><td>{{ __('Session') }}</td><td>{{ $exam->session?->name }}</td></tr>
            </table>
        </div>
        @if(! empty($photo))
            <img class="photo" src="{{ $photo }}" alt="">
        @else
            <div class="ph">{{ __('Photo') }}</div>
        @endif
    </div>
    <table class="mt-2" style="font-size:11px">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Time') }}</th><th>{{ __('Subject') }}</th></tr></thead>
        <tbody>
        @foreach($card['papers'] as $p)
            <tr><td>{{ $p->exam_date?->format('d M Y, D') ?? '—' }}</td><td>{{ $p->starts_at ? substr($p->starts_at, 0, 5) : '' }}@if($p->ends_at)–{{ substr($p->ends_at, 0, 5) }}@endif</td><td>{{ $p->subject?->name }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @if($exam->instructions)<div class="mt-1" style="font-size:10.5px;color:#334155">{!! nl2br(e($exam->instructions)) !!}</div>@endif
    <div class="d-flex justify-content-between align-items-end" style="margin-top:26px">
        <div class="sig" style="width:140px">{{ __('Class teacher') }}</div>
        <div class="sig" style="width:140px">{{ __('Controller of exams') }}</div>
        <div class="sig" style="width:140px">{{ __('Principal') }}</div>
    </div>
</div>
