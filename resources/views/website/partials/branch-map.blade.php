{{-- "All Branch Locations": a Google Map of the selected branch (no API key), an overview map with every pin
     (OpenStreetMap/Leaflet), and a list of all branches with Google Maps and directions links. --}}
@php
    $locations = $branches->filter(fn ($b) => $b->mapQuery())->values();
    $pins = $locations->filter(fn ($b) => $b->hasCoordinates())->map(fn ($b) => [
        'name' => $b->displayName(),
        'address' => (string) $b->address,
        'lat' => (float) $b->latitude,
        'lng' => (float) $b->longitude,
        'url' => $b->url('/'),
        'gmap' => $b->googleMapsUrl(),
        'color' => $b->brandColor(),
    ])->values();
    $current = isset($branch) && $branch instanceof \App\Models\Branch ? $branch->id : null;
    $locations = $locations->sortBy(fn ($b) => $b->id === $current ? 0 : 1)->values();   // the branch being viewed comes first
    $first = $locations->first();
@endphp
@if($locations->isEmpty())
    <div class="site-empty"><i class="fa-solid fa-map-location-dot"></i> {{ __('Branch locations will be published soon.') }}</div>
@else
    <div class="site-wrap">
        <div class="loc" data-locations>
            <ul class="loc-list" aria-label="{{ __('All Branch Locations') }}">
                @foreach($locations as $b)
                    <li class="loc-item {{ $first && $b->id === $first->id ? 'is-active' : '' }}" data-embed="{{ $b->googleEmbedUrl() }}" data-name="{{ $b->displayName() }}">
                        <span class="loc-dot" style="background:{{ $b->brandColor() }}"></span>
                        <div class="loc-body">
                            <strong>{{ $b->displayName() }}</strong>
                            @if($b->address)<span class="loc-addr">{{ $b->address }}</span>@endif
                            @if($b->phone)<span class="loc-addr"><i class="fa-solid fa-phone"></i> {{ $b->phone }}</span>@endif
                            <span class="loc-actions">
                                <button type="button" class="loc-show" data-show><i class="fa-solid fa-map"></i> {{ __('Show on map') }}</button>
                                <a href="{{ $b->googleMapsUrl() }}" target="_blank" rel="noopener"><i class="fa-brands fa-google"></i> {{ __('Google Maps') }}</a>
                                <a href="{{ $b->googleDirectionsUrl() }}" target="_blank" rel="noopener"><i class="fa-solid fa-diamond-turn-right"></i> {{ __('Directions') }}</a>
                                <a href="{{ $b->url('/') }}"><i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Visit branch') }}</a>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="loc-map">
                <div class="loc-tabs" role="tablist">
                    <button type="button" class="is-active" data-tab="google" role="tab"><i class="fa-brands fa-google"></i> {{ __('Google Map') }}</button>
                    @if($pins->count() > 1)<button type="button" data-tab="all" role="tab"><i class="fa-solid fa-location-dot"></i> {{ __('All branches') }}</button>@endif
                </div>
                <iframe class="loc-frame" data-frame src="{{ $first?->googleEmbedUrl() }}" title="{{ __('Google Map') }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                @if($pins->count() > 1)<div id="branchMap" class="branch-map loc-overview" data-overview hidden role="region" aria-label="{{ __('All Branch Locations') }}"></div>@endif
            </div>
        </div>
    </div>
    @if($pins->count() > 1)
        @push('head')
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
        @endpush
    @endif
    @push('scripts')
        @if($pins->count() > 1)<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>@endif
        <script>
            (function () {
                var root = document.querySelector('[data-locations]');
                if (!root) { return; }
                var frame = root.querySelector('[data-frame]'), overview = root.querySelector('[data-overview]');
                var items = root.querySelectorAll('.loc-item'), tabs = root.querySelectorAll('[data-tab]');
                var map = null;

                function select(item) {
                    items.forEach(function (i) { i.classList.toggle('is-active', i === item); });
                    frame.src = item.dataset.embed;
                    tab('google');
                }
                function tab(name) {
                    tabs.forEach(function (t) { t.classList.toggle('is-active', t.dataset.tab === name); });
                    frame.hidden = name !== 'google';
                    if (overview) { overview.hidden = name !== 'all'; }
                    if (name === 'all') { initMap(); }
                }
                items.forEach(function (item) {
                    item.querySelector('[data-show]').addEventListener('click', function () { select(item); if (window.innerWidth < 900) { root.querySelector('.loc-map').scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } });
                });
                tabs.forEach(function (t) { t.addEventListener('click', function () { tab(t.dataset.tab); }); });

                function initMap() {
                    if (!overview || typeof L === 'undefined') { return; }
                    if (map) { map.invalidateSize(); return; }
                    var pts = @json($pins);
                    map = L.map(overview, { scrollWheelZoom: false });
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
                    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
                    var bounds = [];
                    pts.forEach(function (p) {
                        var icon = L.divIcon({ className: 'branch-pin', html: '<i class="fa-solid fa-location-dot" style="color:' + p.color + '"></i>', iconSize: [30, 36], iconAnchor: [15, 34], popupAnchor: [0, -30] });
                        L.marker([p.lat, p.lng], { icon: icon, title: p.name }).addTo(map)
                            .bindPopup('<strong>' + esc(p.name) + '</strong><br>' + esc(p.address) + '<br><a href="' + encodeURI(p.gmap) + '" target="_blank" rel="noopener">{{ __('Google Maps') }}</a> · <a href="' + encodeURI(p.url) + '">{{ __('Visit branch') }}</a>');
                        bounds.push([p.lat, p.lng]);
                    });
                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
                    map.on('focus', function () { map.scrollWheelZoom.enable(); });
                    map.on('blur', function () { map.scrollWheelZoom.disable(); });
                }
            })();
        </script>
    @endpush
@endif
