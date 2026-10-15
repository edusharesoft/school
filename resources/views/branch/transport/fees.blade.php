@extends('layouts.branch')
@section('title', __('Transport fees'))

@section('content')
<x-page-header :title="__('Transport fees')" :breadcrumbs="[__('Transport') => route('branch.transport.index'), __('Fees') => null]" />
@include('branch.partials.service-fees', ['routeName' => 'branch.transport.fees', 'generateRoute' => 'branch.transport.fees.generate', 'can' => 'transport.create', 'period' => $period, 'invoices' => $invoices, 'totals' => $totals,
    'note' => __('One invoice per student seat and month, at the seat\'s monthly fee (a month the seat touches is billed in full). Invoices appear with the other fees: collect them under Fees & accounts.')])
@endsection
