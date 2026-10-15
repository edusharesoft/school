@extends('layouts.branch')
@section('title', __('Hostel fees'))

@section('content')
<x-page-header :title="__('Hostel fees')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), __('Fees') => null]" />
@include('branch.partials.service-fees', ['routeName' => 'branch.hostel.fees', 'generateRoute' => 'branch.hostel.fees.generate', 'can' => 'hostel.create', 'period' => $period, 'invoices' => $invoices, 'totals' => $totals,
    'note' => __('One invoice per resident and month: the room fee plus the meal plan, as allocated (a month the stay touches is billed in full). Collect them under Fees & accounts.')])
@endsection
