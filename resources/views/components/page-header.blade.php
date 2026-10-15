@props(['title', 'breadcrumbs' => []])
<div class="page-header">
    <div>
        @if($breadcrumbs)
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    @foreach($breadcrumbs as $label => $url)
                        @if($url)
                            <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                        @else
                            <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1>{{ $title }}</h1>
    </div>
    @isset($actions)
        <div class="actions">{{ $actions }}</div>
    @endisset
</div>
