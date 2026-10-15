@php($cur = app()->getLocale())
<div class="dropdown d-inline-block">
    <a href="#" class="text-decoration-none {{ $class ?? 'text-dark' }} dropdown-toggle small" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Language') }}">
        <i class="fa-solid fa-language"></i> {{ $cur === 'bn' ? 'বাংলা' : 'English' }}
    </a>
    <ul class="dropdown-menu dropdown-menu-end small">
        <li><a class="dropdown-item {{ $cur === 'en' ? 'active' : '' }}" href="{{ route('locale.switch', 'en') }}">English</a></li>
        <li><a class="dropdown-item bn {{ $cur === 'bn' ? 'active' : '' }}" href="{{ route('locale.switch', 'bn') }}">বাংলা</a></li>
    </ul>
</div>
