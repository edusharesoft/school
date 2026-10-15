@extends('layouts.public')
@section('title', __('Exam results'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('results.lookup') }}" class="dp green mb-4">
            @csrf
            <div class="dp-head"><h2><i class="fa-solid fa-square-poll-vertical me-1"></i> {{ __('Find your result') }}</h2></div>
            <div class="dp-body">
                @if($exams->isEmpty())
                    <p class="text-body-secondary mb-0">{{ __('No results have been published yet.') }}</p>
                @else
                    <div class="mb-3">
                        <label class="form-label" for="r_exam">{{ __('Exam') }}</label>
                        <select id="r_exam" name="examination_id" class="form-select @error('examination_id') is-invalid @enderror" required>
                            @foreach($exams as $e)<option value="{{ $e->id }}" @selected((int) old('examination_id') === $e->id)>{{ $e->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="r_code">{{ __('Student ID') }}</label>
                        <input id="r_code" name="student_code" value="{{ old('student_code') }}" class="form-control text-uppercase @error('student_code') is-invalid @enderror" required maxlength="40" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="r_dob">{{ __('Date of birth') }}</label>
                        <input id="r_dob" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="form-control @error('date_of_birth') is-invalid @enderror" required>
                    </div>
                    <button class="btn btn-brand w-100">{{ __('Show result') }}</button>
                @endif
            </div>
        </form>
        @if(! empty($notFound))
            <div class="alert alert-danger"><i class="fa-solid fa-circle-xmark me-1"></i> {{ __('No published result matches this student ID and date of birth.') }}</div>
        @endif
    </div>
</div>
@endsection
