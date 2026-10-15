@extends($layout)
@section('title', __('Learning progress').' — '.$student->name)

@section('content')
<x-page-header :title="$student->name" :breadcrumbs="[__('Learning progress') => route($routePrefix.'learning.progress.index'), $student->student_code => null]" />
@include('portal.learning.partials.progress-body', ['progress' => $progress, 'assignmentUrl' => null])
@endsection
