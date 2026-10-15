@extends('layouts.portal')
@section('title', $quiz->title)

@section('content')
<x-page-header :title="$quiz->title" :breadcrumbs="[__('Quizzes') => route('portal.student.quizzes'), $quiz->title => null]" />

<form method="POST" action="{{ route('portal.student.quizzes.submit', $attempt) }}" id="quiz-form" style="max-width: 860px"
      x-data="quizTake({ questions: @js($questions), saved: @js((object) $saved), seconds: @js($secondsLeft), saveUrl: @js(route('portal.student.quizzes.save', $attempt)) })" x-init="start()" @submit="submitting = true">
    @csrf
    <div class="position-sticky top-0 py-2 mb-3" style="z-index: 5; background: var(--bs-body-bg)">
        <div class="d-flex justify-content-between align-items-center gap-3 border rounded p-2 bg-body">
            <div class="small"><span x-text="answered()"></span> / <span x-text="questions.length"></span> {{ __('answered') }} <span class="text-body-secondary ms-2" x-text="status"></span></div>
            <template x-if="seconds !== null"><div class="fw-bold fs-5" :class="seconds < 60 ? 'text-danger' : ''" x-text="clock()" role="timer" aria-live="off"></div></template>
        </div>
    </div>

    <template x-for="(q, qi) in questions" :key="q.id">
        <div class="dp blue mb-3">
            <div class="dp-head"><h2><span x-text="qi + 1"></span>. <span x-text="q.text" style="white-space: pre-line"></span></h2><span class="sub" x-text="q.marks + ' {{ __('marks') }}'"></span></div>
            <div class="dp-body">
                <div class="small text-body-secondary mb-2" x-show="q.type === 'mcq_multi'">{{ __('Choose all that apply.') }}</div>
                <template x-for="(opt, oi) in q.options" :key="oi">
                    <label class="d-flex align-items-start gap-2 border rounded p-2 mb-2" style="cursor:pointer">
                        <input :type="q.type === 'mcq_multi' ? 'checkbox' : 'radio'" class="form-check-input mt-1" :name="`answers[${q.id}][]`" :value="oi" :checked="has(q.id, oi)" @change="pick(q, oi, $event.target.checked)">
                        <span x-text="q.type === 'true_false' ? (oi === 0 ? @js(__('True')) : @js(__('False'))) : opt"></span>
                    </label>
                </template>
            </div>
        </div>
    </template>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success" :disabled="submitting" @click="confirmSubmit($event)"><i class="bi bi-check2-circle me-1"></i>{{ __('Hand in') }}</button>
    </div>
</form>

<script>
function quizTake(init) {
    return {
        questions: init.questions, answers: JSON.parse(JSON.stringify(init.saved || {})), seconds: init.seconds, status: '', submitting: false, timer: null, saveTimer: null,
        start() {
            if (this.seconds !== null) {
                this.timer = setInterval(() => {
                    this.seconds = Math.max(0, this.seconds - 1);
                    if (this.seconds === 0) { clearInterval(this.timer); this.autoSubmit(); }
                }, 1000);
            }
        },
        has(id, i) { return (this.answers[id] || []).includes(i); },
        pick(q, i, on) {
            const cur = this.answers[q.id] || [];
            this.answers[q.id] = q.type === 'mcq_multi' ? (on ? [...new Set([...cur, i])] : cur.filter(x => x !== i)) : [i];
            this.queueSave();
        },
        answered() { return this.questions.filter(q => (this.answers[q.id] || []).length).length; },
        clock() { const m = Math.floor(this.seconds / 60), s = this.seconds % 60; return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0'); },
        queueSave() { clearTimeout(this.saveTimer); this.saveTimer = setTimeout(() => this.save(), 600); },
        async save() {
            try {
                const token = document.querySelector('meta[name=csrf-token]')?.content;
                const res = await fetch(init.saveUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ answers: this.answers }) });
                this.status = res.ok ? @js(__('Saved')) : @js(__('Not saved — check your connection'));
            } catch (e) { this.status = @js(__('Not saved — check your connection')); }
        },
        confirmSubmit(e) {
            const left = this.questions.length - this.answered();
            if (left > 0 && !confirm(@js(__('Some questions are not answered. Hand in anyway?')))) { e.preventDefault(); return false; }
            return true;
        },
        autoSubmit() { this.submitting = true; this.$nextTick(() => document.getElementById('quiz-form').requestSubmit()); },
    };
}
</script>
@endsection
