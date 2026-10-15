@extends($layout)
@section('title', $quiz->exists ? __('Edit quiz') : __('New quiz'))

@section('content')
<x-page-header :title="$quiz->exists ? __('Edit quiz') : __('New quiz')" :breadcrumbs="[__('Quizzes') => route($routePrefix.'learning.quizzes.index'), ($quiz->exists ? __('Edit') : __('New')) => null]" />

<form method="POST" style="max-width: 980px"
      action="{{ $quiz->exists ? route($routePrefix.'learning.quizzes.update', $quiz) : route($routePrefix.'learning.quizzes.store') }}"
      x-data="quizForm({ cls: @js((string) old('school_class_id', $quiz->school_class_id)), sections: @js($sections), subjects: @js($subjects), questions: @js($questionsData), locked: @js($locked) })">
    @csrf @if($quiz->exists) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Quiz details') }}</strong></div>
        <div class="card-body row g-3">
            <x-form.input name="title" :label="__('Title')" :value="$quiz->title" required col="col-12" maxlength="191" />
            <div class="col-md-4">
                <label class="form-label" for="school_class_id">{{ __('Class') }} <span class="text-danger">*</span></label>
                <select id="school_class_id" name="school_class_id" class="form-select @error('school_class_id') is-invalid @enderror" required x-model="cls">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach($classes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                @error('school_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="section_id">{{ __('Section') }}@if($sectionRequired) <span class="text-danger">*</span>@endif</label>
                <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror" @required($sectionRequired)>
                    @unless($sectionRequired)<option value="">{{ __('Whole class') }}</option>@endunless
                    <template x-for="s in sections.filter(s => String(s.class) === String(cls))" :key="s.id">
                        <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('section_id', $quiz->section_id))"></option>
                    </template>
                </select>
                @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="subject_id">{{ __('Subject') }}</label>
                <select id="subject_id" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror">
                    <option value="">{{ __('General / none') }}</option>
                    <template x-for="s in subjects.filter(s => String(s.class) === String(cls))" :key="s.id">
                        <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('subject_id', $quiz->subject_id))"></option>
                    </template>
                </select>
                @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="instructions">{{ __('Instructions') }}</label>
                <textarea id="instructions" name="instructions" rows="2" maxlength="5000" class="form-control">{{ old('instructions', $quiz->instructions) }}</textarea>
            </div>
            <x-form.input name="time_limit_minutes" type="number" min="1" max="600" :label="__('Time limit (minutes, empty = none)')" :value="$quiz->time_limit_minutes" col="col-md-3" />
            <x-form.input name="max_attempts" type="number" min="1" max="10" :label="__('Attempts allowed')" :value="$quiz->max_attempts" required col="col-md-3" />
            <x-form.input name="pass_percent" type="number" min="0" max="100" step="0.5" :label="__('Pass mark (%)')" :value="$quiz->pass_percent !== null ? $quiz->pass_percent + 0 : null" col="col-md-3" />
            <div class="col-md-3">
                <label class="form-label" for="show_answers">{{ __('Show correct answers') }}</label>
                <select id="show_answers" name="show_answers" class="form-select">
                    @foreach(['never' => __('Never'), 'after_submit' => __('After each attempt'), 'after_close' => __('After the quiz is closed')] as $k => $label)<option value="{{ $k }}" @selected(old('show_answers', $quiz->show_answers) === $k)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <x-form.input name="available_from" type="datetime-local" :label="__('Opens (empty = immediately)')" :value="$quiz->available_from?->format('Y-m-d\TH:i')" col="col-md-4" />
            <x-form.input name="available_until" type="datetime-local" :label="__('Closes (empty = when you close it)')" :value="$quiz->available_until?->format('Y-m-d\TH:i')" col="col-md-4" />
            <div class="col-md-4 d-flex align-items-end"><x-form.switch name="shuffle_questions" :label="__('Shuffle question order')" :checked="$quiz->shuffle_questions" /></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center"><strong>{{ __('Questions') }} <span class="badge text-bg-light border" x-text="questions.length"></span></strong>
            <span class="small text-body-secondary">{{ __('Total marks') }}: <strong x-text="total()"></strong></span></div>
        <div class="card-body">
            @error('questions')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
            <div class="alert alert-info py-2 small" x-show="locked">{{ __('Students have already started this quiz, so the questions can no longer be changed.') }}</div>
            <template x-for="(q, qi) in questions" :key="q.key">
                <fieldset class="border rounded p-3 mb-3" :disabled="locked">
                    <div class="row g-2 align-items-start">
                        <div class="col-auto"><span class="badge text-bg-primary" x-text="qi + 1"></span></div>
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" :name="`questions[${qi}][type]`" x-model="q.type" @change="typeChanged(q)" aria-label="{{ __('Question type') }}">
                                <option value="mcq_single">{{ __('Multiple choice — one answer') }}</option>
                                <option value="mcq_multi">{{ __('Multiple choice — several answers') }}</option>
                                <option value="true_false">{{ __('True / False') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2"><input type="number" step="0.5" min="0.5" class="form-control form-control-sm" :name="`questions[${qi}][marks]`" x-model="q.marks" aria-label="{{ __('Marks') }}" placeholder="{{ __('Marks') }}"></div>
                        <div class="col text-end"><button type="button" class="btn btn-sm btn-outline-danger" @click="questions.splice(qi, 1)" aria-label="{{ __('Remove question') }}"><i class="bi bi-trash"></i></button></div>
                        <div class="col-12"><textarea rows="2" class="form-control" :name="`questions[${qi}][text]`" x-model="q.text" maxlength="2000" placeholder="{{ __('Write the question') }}" aria-label="{{ __('Question') }}"></textarea></div>

                        <template x-if="q.type === 'true_false'">
                            <div class="col-12 d-flex gap-4">
                                <label class="form-check"><input type="radio" class="form-check-input" :name="`questions[${qi}][correct][]`" value="0" :checked="q.correct.includes(0)" @change="q.correct = [0]"> <span class="form-check-label">{{ __('True') }}</span></label>
                                <label class="form-check"><input type="radio" class="form-check-input" :name="`questions[${qi}][correct][]`" value="1" :checked="q.correct.includes(1)" @change="q.correct = [1]"> <span class="form-check-label">{{ __('False') }}</span></label>
                                <span class="small text-body-secondary">{{ __('Choose the correct answer') }}</span>
                            </div>
                        </template>
                        <template x-if="q.type !== 'true_false'">
                            <div class="col-12">
                                <template x-for="(opt, oi) in q.options" :key="oi">
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">
                                            <input :type="q.type === 'mcq_multi' ? 'checkbox' : 'radio'" :name="q.type === 'mcq_multi' ? `questions[${qi}][correct][]` : `questions[${qi}][correct][]`" :value="oi"
                                                   :checked="q.correct.includes(oi)" @change="toggleCorrect(q, oi, $event.target.checked)" :aria-label="`{{ __('Correct answer') }} ${oi + 1}`">
                                        </span>
                                        <input type="text" class="form-control" :name="`questions[${qi}][options][${oi}]`" x-model="q.options[oi]" maxlength="500" :placeholder="`{{ __('Option') }} ${oi + 1}`">
                                        <button type="button" class="btn btn-outline-secondary" @click="removeOption(q, oi)" x-show="q.options.length > 2" aria-label="{{ __('Remove option') }}"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-sm btn-link px-0" @click="q.options.push('')" x-show="q.options.length < 8"><i class="bi bi-plus"></i> {{ __('Add option') }}</button>
                                <div class="form-text">{{ __('Tick the correct option(s). Blank options are ignored.') }}</div>
                            </div>
                        </template>
                        <div class="col-12"><input type="text" class="form-control form-control-sm" :name="`questions[${qi}][explanation]`" x-model="q.explanation" maxlength="1000" placeholder="{{ __('Explanation shown with the answer (optional)') }}" aria-label="{{ __('Explanation') }}"></div>
                    </div>
                </fieldset>
            </template>
            <button type="button" class="btn btn-outline-primary btn-sm" @click="addQuestion()" x-show="!locked"><i class="bi bi-plus-lg me-1"></i>{{ __('Add question') }}</button>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-brand" type="submit">{{ __('Save') }}</button>
        @unless($quiz->exists)<button class="btn btn-success" type="submit" name="publish_now" value="1">{{ __('Save and publish') }}</button>@endunless
        <a href="{{ route($routePrefix.'learning.quizzes.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
function quizForm(init) {
    let key = 0;
    const blank = () => ({ key: ++key, type: 'mcq_single', text: '', options: ['', '', '', ''], correct: [], marks: 1, explanation: '' });
    const norm = (q) => {
        const o = q.type === 'true_false' ? [] : [...(q.options || [])];
        while (o.length < 4) o.push('');
        return { key: ++key, type: q.type || 'mcq_single', text: q.text || '', options: o, correct: (q.correct || []).map(Number), marks: q.marks ?? 1, explanation: q.explanation || '' };
    };
    return {
        cls: init.cls, sections: init.sections, subjects: init.subjects, locked: init.locked,
        questions: (init.questions && init.questions.length ? init.questions : [{}]).map(q => q.type ? norm(q) : blank()),
        addQuestion() { this.questions.push(blank()); },
        total() { return this.questions.reduce((s, q) => s + (parseFloat(q.marks) || 0), 0); },
        typeChanged(q) { q.correct = []; if (q.type !== 'true_false' && q.options.length < 2) q.options = ['', '', '', '']; },
        toggleCorrect(q, i, on) { if (q.type === 'mcq_multi') { q.correct = on ? [...new Set([...q.correct, i])] : q.correct.filter(x => x !== i); } else { q.correct = [i]; } },
        removeOption(q, i) { q.options.splice(i, 1); q.correct = q.correct.filter(x => x !== i).map(x => x > i ? x - 1 : x); },
    };
}
</script>
@endsection
