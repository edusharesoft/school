{{-- Wording of a certificate. Needs $certificate; shared by the print view and the PDF. --}}
@php
    $d = $certificate->data;
    $he = ($certificate->student?->gender ?? 'male') === 'female' ? ['she', 'her', 'daughter'] : ['he', 'his', 'son'];
    $parent = $d['father'] ?? $d['guardian'] ?? '—';
@endphp
@if($certificate->type === 'study')
    {{ __('This is to certify that :name, :relation of :parent, Student ID :code, is a regular student of this institution, studying in class :class (section :section, roll :roll) in the :session session. Date of birth: :dob.', [
        'name' => $d['name'], 'relation' => __($he[2]), 'parent' => $parent, 'code' => $d['student_code'], 'class' => $d['class'] ?? '—',
        'section' => $d['section'] ?? '—', 'roll' => $d['roll_no'] ?? '—', 'session' => $d['session'] ?? '—', 'dob' => isset($d['date_of_birth']) ? \Illuminate\Support\Carbon::parse($d['date_of_birth'])->format('d M Y') : '—',
    ]) }}
@elseif($certificate->type === 'character')
    {{ __('This is to certify that :name, :relation of :parent, Student ID :code, has been a student of this institution (class :class, session :session). To the best of my knowledge :pronoun bears a good moral character. Conduct: :conduct.', [
        'name' => $d['name'], 'relation' => __($he[2]), 'parent' => $parent, 'code' => $d['student_code'], 'class' => $d['class'] ?? '—',
        'session' => $d['session'] ?? '—', 'pronoun' => __($he[0]), 'conduct' => $d['conduct'] ?? __('Good'),
    ]) }}
@elseif($certificate->type === 'achievement')
    {{ __('This is to certify that :name, :relation of :parent, Student ID :code, of class :class (session :session) has been awarded this certificate for :title.', [
        'name' => $d['name'], 'relation' => __($he[2]), 'parent' => $parent, 'code' => $d['student_code'], 'class' => $d['class'] ?? '—',
        'session' => $d['session'] ?? '—', 'title' => $d['title'] ?? '—',
    ]) }}
    @if(! empty($d['details']))<div class="mt-2">{{ $d['details'] }}</div>@endif
@else
    {{ __('This is to certify that :name, :relation of :parent, Student ID :code, was a student of this institution from :admitted to :left. At the time of leaving :pronoun was studying in class :class (section :section, roll :roll), session :session. Date of birth as per our register: :dob. Reason for leaving: :reason. Conduct: :conduct. All dues have been cleared.', [
        'name' => $d['name'], 'relation' => __($he[2]), 'parent' => $parent, 'code' => $d['student_code'],
        'admitted' => isset($d['admission_date']) ? \Illuminate\Support\Carbon::parse($d['admission_date'])->format('d M Y') : '—',
        'left' => isset($d['leaving_date']) ? \Illuminate\Support\Carbon::parse($d['leaving_date'])->format('d M Y') : '—',
        'pronoun' => __($he[0]), 'class' => $d['class'] ?? '—', 'section' => $d['section'] ?? '—', 'roll' => $d['roll_no'] ?? '—', 'session' => $d['session'] ?? '—',
        'dob' => isset($d['date_of_birth']) ? \Illuminate\Support\Carbon::parse($d['date_of_birth'])->format('d M Y') : '—',
        'reason' => $d['reason'] ?? __('Guardian’s request'), 'conduct' => $d['conduct'] ?? __('Good'),
    ]) }}
@endif
@if(! empty($d['remarks']))<div class="mt-2">{{ __('Remarks') }}: {{ $d['remarks'] }}</div>@endif
<div class="mt-2">{{ __('I wish :pronoun every success in life.', ['pronoun' => __($he[1] === 'her' ? 'her' : 'him')]) }}</div>
    