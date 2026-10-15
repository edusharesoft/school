@extends('layouts.branch')
@section('title', __('Circulation desk'))

@section('content')
<x-page-header :title="__('Circulation desk')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Circulation desk') => null]" />

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"
             x-data="{ q: '', results: [], picked: @js($member ? ['key' => $memberKey, 'name' => $member->name, 'code' => \App\Services\Library\LibraryMembers::code($member)] : null),
                       async search() { if (this.q.trim().length < 2) { this.results = []; return; }
                         const r = await fetch(@js(route('branch.library.desk.members')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                         this.results = r.ok ? await r.json() : []; },
                       pick(m) { window.location = @js(route('branch.library.desk')) + '?member=' + encodeURIComponent(m.key); } }">
            <div class="card-header bg-transparent fw-semibold">1. {{ __('Member') }}</div>
            <div class="card-body">
                <template x-if="picked"><div class="d-flex justify-content-between align-items-center">
                    <div><div class="fw-semibold" x-text="picked.name"></div><div class="small text-body-secondary" x-text="picked.code"></div></div>
                    <a href="{{ route('branch.library.desk') }}" class="btn btn-sm btn-outline-secondary">{{ __('Change') }}</a>
                </div></template>
                <div x-show="!picked">
                    <input type="search" x-model="q" @input.debounce.300ms="search()" class="form-control" autofocus placeholder="{{ __('Search a student or staff member by name, ID or phone') }}" aria-label="{{ __('Search member') }}">
                    <div class="list-group mt-2" x-show="results.length">
                        <template x-for="m in results" :key="m.key">
                            <button type="button" class="list-group-item list-group-item-action small" @click="pick(m)">
                                <span class="fw-semibold" x-text="m.name"></span> <span class="text-body-secondary" x-text="(m.code || '') + (m.detail ? ' · ' + m.detail : '')"></span>
                                <span class="badge text-bg-light float-end" x-text="m.type"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('branch.library.issue') }}" class="card mb-3">
            @csrf
            <input type="hidden" name="member" value="{{ $memberKey }}">
            <div class="card-header bg-transparent fw-semibold">2. {{ __('Issue a book') }}</div>
            <div class="card-body row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="issue_code">{{ __('Barcode / accession number') }}</label>
                    <input id="issue_code" name="code" value="{{ old('code') }}" class="form-control @error('code') is-invalid @enderror" autocomplete="off" @disabled(! $member) required>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('member')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="due_on">{{ __('Due date (optional)') }}</label>
                    <input id="due_on" type="date" name="due_on" value="{{ old('due_on') }}" min="{{ now()->toDateString() }}" class="form-control @error('due_on') is-invalid @enderror" @disabled(! $member)>
                    @error('due_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-brand" @disabled(! $member)><i class="bi bi-box-arrow-right me-1"></i>{{ __('Issue') }}</button></div>
        </form>

        <form method="POST" action="{{ route('branch.library.return') }}" class="card" x-data="{ condition: 'good' }">
            @csrf
            <div class="card-header bg-transparent fw-semibold">{{ __('Return a book') }}</div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="return_code">{{ __('Barcode / accession number') }}</label>
                    <input id="return_code" name="code" class="form-control" autocomplete="off" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="condition">{{ __('Condition') }}</label>
                    <select id="condition" name="condition" class="form-select" x-model="condition">
                        <option value="good">{{ __('Good') }}</option><option value="damaged">{{ __('Damaged') }}</option><option value="lost">{{ __('Lost') }}</option>
                    </select>
                </div>
                <div class="col-md-3" x-show="condition === 'damaged'">
                    <label class="form-label" for="damage_fee">{{ __('Damage fee') }}</label>
                    <input id="damage_fee" type="number" step="0.01" min="0" name="damage_fee" class="form-control">
                </div>
                <input type="hidden" name="member" value="{{ $memberKey }}">
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-left me-1"></i>{{ __('Return') }}</button></div>
        </form>
    </div>

    <div class="col-lg-6">
        @if($member && $account)
            @include('branch.library.partials.account', ['member' => $member, 'account' => $account, 'type' => \App\Services\Library\LibraryMembers::type($member), 'returnable' => true])
        @else
            <div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Latest activity') }}</div>
                @if($recent->isEmpty())<x-empty-state icon="upc-scan" :title="__('Nothing issued yet')" :message="__('Pick a member and scan a book to start.')" />
                @else<ul class="list-group list-group-flush small">@foreach($recent as $t)
                    <li class="list-group-item"><span class="badge text-bg-{{ $t->status === 'issued' ? 'primary' : ($t->status === 'lost' ? 'danger' : 'secondary') }} me-1">{{ __(ucfirst($t->status)) }}</span>
                        {{ $t->book?->displayTitle() }} — {{ $t->memberModel()?->name ?? '—' }}<span class="text-body-secondary float-end">{{ $t->updated_at->diffForHumans() }}</span></li>
                @endforeach</ul>@endif
            </div>
        @endif
    </div>
</div>
@endsection
