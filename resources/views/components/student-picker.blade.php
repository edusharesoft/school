{{-- Search-as-you-type picker of an active student. Posts the chosen id as $name; $gender (optional) is exposed to the outer Alpine scope as "pickedGender". --}}
@props(['name' => 'student_id', 'label' => null, 'col' => 'col-md-6'])
<div class="{{ $col }}" x-data="{ q: '', results: [], id: @js(old($name)), picked: null,
        async search() { if (this.q.trim().length < 2) { this.results = []; return; }
            const r = await fetch(@js(route('branch.operations.students')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
            this.results = r.ok ? await r.json() : []; },
        pick(s) { this.id = s.id; this.picked = s; this.results = []; this.q = ''; } }">
    <label class="form-label">{{ $label ?? __('Student') }} <span class="text-danger">*</span></label>
    <input type="hidden" name="{{ $name }}" :value="id">
    <template x-if="picked"><div class="d-flex justify-content-between align-items-center border rounded px-2 py-1 small">
        <span><strong x-text="picked.name"></strong> <span class="text-body-secondary" x-text="(picked.code || '') + (picked.detail ? ' · ' + picked.detail : '')"></span></span>
        <button type="button" class="btn btn-sm btn-link" @click="picked = null; id = null">{{ __('Change') }}</button></div></template>
    <div x-show="!picked">
        <input type="search" x-model="q" @input.debounce.300ms="search()" class="form-control form-control-sm @error($name) is-invalid @enderror" placeholder="{{ __('Search by name, ID or phone') }}" aria-label="{{ __('Search student') }}" autocomplete="off">
        <div class="list-group mt-1" x-show="results.length"><template x-for="s in results" :key="s.id">
            <button type="button" class="list-group-item list-group-item-action small" @click="pick(s)"><span x-text="s.name"></span> <span class="text-body-secondary" x-text="(s.code || '') + (s.detail ? ' · ' + s.detail : '')"></span></button></template></div>
    </div>
    @error($name)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
