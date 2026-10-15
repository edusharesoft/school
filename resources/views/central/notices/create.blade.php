@extends('layouts.central')
@section('title', __('New institution notice'))

@section('content')
<x-page-header :title="__('New institution notice')" :breadcrumbs="[__('Institution notices') => route('central.notices.index'), __('New') => null]" />

<form method="POST" action="{{ route('central.notices.store') }}" enctype="multipart/form-data" class="card" style="max-width: 980px">
    @csrf
    <div class="card-body row g-3">
        <x-form.input name="title" :label="__('Title')" required col="col-md-8" maxlength="191" />
        <x-form.select name="category" :label="__('Category')" value="general" required col="col-md-4"
            :options="collect(App\Models\Communication\Notice::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __(ucfirst($c))])->all()" />
        <div class="col-12">
            <label class="form-label" for="body">{{ __('Notice text') }} <span class="text-danger">*</span></label>
            <textarea id="body" name="body" rows="9" maxlength="10000" required class="form-control @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-form.select name="audience" :label="__('Audience')" value="all" required col="col-md-4"
            :options="collect(['all', 'staff', 'teachers', 'students', 'guardians'])->mapWithKeys(fn ($a) => [$a => __(ucfirst($a))])->all()" />
        <x-form.input name="publish_at" type="datetime-local" :label="__('Publish at (empty = now)')" col="col-md-4" />
        <x-form.input name="expires_at" type="datetime-local" :label="__('Expires at (optional)')" col="col-md-4" />
        <div class="col-12">
            <div class="form-label mb-1">{{ __('Branches') }} <span class="text-danger">*</span></div>
            <div class="row g-1">
                @foreach($branches as $b)
                    <div class="col-md-4"><div class="form-check">
                        <input class="form-check-input" type="checkbox" name="branch_ids[]" value="{{ $b->id }}" id="br_{{ $b->id }}" @checked(in_array((string) $b->id, (array) old('branch_ids', $branches->pluck('id')->map(fn ($i) => (string) $i)->all()), true))>
                        <label class="form-check-label" for="br_{{ $b->id }}">{{ $b->name }} <span class="text-body-secondary">({{ $b->code }})</span></label>
                    </div></div>
                @endforeach
            </div>
            @error('branch_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="attachment">{{ __('Attachment (optional, max 10 MB)') }}</label>
            <input id="attachment" name="attachment" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
        </div>
        <div class="col-md-6">
            <div class="form-label mb-1">{{ __('Also send by (where the branch has the channel switched on)') }}</div>
            @foreach(['sms' => __('SMS'), 'email' => __('E-mail'), 'whatsapp' => __('WhatsApp')] as $ch => $label)
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="channels[]" value="{{ $ch }}" id="ch_{{ $ch }}" @disabled(! auth()->user()->can('notifications.send'))><label class="form-check-label" for="ch_{{ $ch }}">{{ $label }}</label></div>
            @endforeach
        </div>
        <x-form.switch name="is_emergency" :label="__('Emergency notice')" col="col-md-6" />
        <x-form.switch name="is_pinned" :label="__('Pin to the top of the board')" col="col-md-6" />
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        @can('notices.publish')<button class="btn btn-brand" name="publish_now" value="1">{{ __('Send to branches and publish') }}</button>@endcan
        <button class="btn btn-outline-primary">{{ __('Send to branches as drafts') }}</button>
        <a href="{{ route('central.notices.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
