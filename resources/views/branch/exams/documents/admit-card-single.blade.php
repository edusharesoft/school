{{-- A student's own admit card (student portal). --}}
@extends('layouts.base')
@section('title', __('Admit card').' — '.$exam->name)

@include('branch.exams.documents._print-styles')
@include('branch.exams.documents._admit-styles')

@section('body')
@include('branch.exams.documents._toolbar', ['back' => $back])
<div class="sheet" style="padding-top:14px">
    @include('branch.exams.documents._admit-card', ['photo' => null])
</div>
@endsection
