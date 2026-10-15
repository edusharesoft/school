{{-- Admin editor of the hero banner slides (central website and branch websites). Expects $slides (list from HeroSlides::effective). --}}
@use('App\Support\Website')
@use('App\Services\Website\HeroSlides')
@php($slides = array_values(array_filter($slides, fn ($s) => Website::img($s['image'] ?? null))))
<div class="col-12"><h2 class="h6 mb-0">{{ __('Hero banner slides') }}</h2>
    <div class="form-text">{{ __('Add up to :max pictures; they slide automatically on the home page. Wide landscape photos, about 1920×800 px, up to 6 MB each.', ['max' => HeroSlides::MAX]) }}</div></div>

@foreach($slides as $i => $s)
    <div class="col-md-6 col-lg-4">
        <div class="border rounded p-2 h-100">
            <input type="hidden" name="slides[{{ $i }}][path]" value="{{ $s['image'] }}">
            <img src="{{ Website::img($s['image']) }}" alt="" class="img-fluid rounded mb-2" style="aspect-ratio:16/6;object-fit:cover;width:100%">
            <div class="row g-2">
                <div class="col-4"><label class="form-label small mb-0" for="sl{{ $i }}_pos">{{ __('Order') }}</label>
                    <input id="sl{{ $i }}_pos" type="number" min="0" max="99" name="slides[{{ $i }}][position]" value="{{ old("slides.$i.position", $i + 1) }}" class="form-control form-control-sm"></div>
                <div class="col-8"><label class="form-label small mb-0" for="sl{{ $i }}_title">{{ __('Caption title') }}</label>
                    <input id="sl{{ $i }}_title" name="slides[{{ $i }}][title]" maxlength="120" value="{{ old("slides.$i.title", $s['title'] ?? '') }}" class="form-control form-control-sm"></div>
                <div class="col-12"><label class="form-label small mb-0" for="sl{{ $i }}_text">{{ __('Caption text') }}</label>
                    <input id="sl{{ $i }}_text" name="slides[{{ $i }}][text]" maxlength="200" value="{{ old("slides.$i.text", $s['text'] ?? '') }}" class="form-control form-control-sm"></div>
                <div class="col-12"><label class="form-label small mb-0" for="sl{{ $i }}_link">{{ __('Button link (optional)') }}</label>
                    <input id="sl{{ $i }}_link" name="slides[{{ $i }}][link]" maxlength="255" placeholder="https://" value="{{ old("slides.$i.link", $s['link'] ?? '') }}" class="form-control form-control-sm @error("slides.$i.link") is-invalid @enderror">
                    @error("slides.$i.link")<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><div class="form-check"><input type="hidden" name="slides[{{ $i }}][remove]" value="0"><input class="form-check-input" type="checkbox" name="slides[{{ $i }}][remove]" value="1" id="sl{{ $i }}_rm"><label class="form-check-label small" for="sl{{ $i }}_rm">{{ __('Remove this slide') }}</label></div></div>
            </div>
        </div>
    </div>
@endforeach

<div class="col-12">
    <label class="form-label small" for="hero_slides">{{ __('Add slide pictures') }}</label>
    <input id="hero_slides" type="file" name="hero_slides[]" multiple accept="image/png,image/jpeg,image/webp" class="form-control @error('hero_slides') is-invalid @enderror @error('hero_slides.*') is-invalid @enderror" aria-label="{{ __('Add slide pictures') }}">
    @error('hero_slides')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @error('hero_slides.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($slides === [])<div class="form-text">{{ __('No slides yet — the home page shows a plain banner.') }}</div>@endif
</div>
