{{-- Summary cards, subject averages and the list of work for one student. $assignmentUrl: fn(Assignment) => url|null --}}
@php($p = $progress)
@if(! $p['enrollment'])
    <div class="alert alert-warning">{{ __('Not enrolled in the current session.') }}</div>
@else
<div class="row g-3 mb-3">
    @foreach([
        [__('Work given'), bn_digits($p['total']), 'primary'],
        [__('Handed in'), bn_digits($p['done']).' / '.bn_digits($p['total']), 'success'],
        [__('Missing'), bn_digits($p['missing']), $p['missing'] ? 'danger' : 'secondary'],
        [__('Average marks'), $p['average'] !== null ? bn_digits($p['average']).'%' : '—', 'info'],
        [__('Quizzes'), $p['quizzes']['total'] ? bn_digits($p['quizzes']['taken']).' / '.bn_digits($p['quizzes']['total']).($p['quizzes']['average'] !== null ? ' · '.bn_digits($p['quizzes']['average']).'%' : '') : '—', 'warning'],
        [__('Class attendance'), $p['classes']['percent'] !== null ? bn_digits($p['classes']['percent']).'% ('.bn_digits($p['classes']['attended']).'/'.bn_digits($p['classes']['total']).')' : '—', 'primary'],
    ] as [$label, $value, $tone])
        <div class="col-6 col-md"><div class="panel panel-body text-center"><div class="text-body-secondary small">{{ $label }}</div><div class="h4 mb-0 text-{{ $tone }}">{{ $value }}</div></div></div>
    @endforeach
</div>

@if($p['subjects'])
<div class="dp blue mb-3">
    <div class="dp-head"><h2><i class="fa-solid fa-chart-simple me-1"></i> {{ __('Average marks by subject') }}</h2></div>
    <div class="dp-body">
        @foreach($p['subjects'] as $s)
            <div class="d-flex align-items-center gap-2 mb-2 small">
                <div style="width:30%">{{ $s['name'] }}</div>
                <div class="progress flex-grow-1" role="progressbar" aria-label="{{ $s['name'] }}" aria-valuenow="{{ $s['average'] }}" aria-valuemin="0" aria-valuemax="100" style="height:10px"><div class="progress-bar bg-{{ $s['average'] >= 60 ? 'success' : ($s['average'] >= 40 ? 'warning' : 'danger') }}" style="width: {{ min(100, $s['average']) }}%"></div></div>
                <div style="width:60px" class="text-end">{{ bn_digits($s['average']) }}%</div>
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="dp green">
    <div class="dp-head"><h2><i class="fa-solid fa-book me-1"></i> {{ __('Homework & assignments') }}</h2></div>
    <div class="dp-body p-0">
        @if($p['items']->isEmpty())
            <p class="text-body-secondary p-3 mb-0">{{ __('No work has been given yet.') }}</p>
        @else
        <div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Due') }}</th><th>{{ __('State') }}</th><th class="text-end">{{ __('Marks') }}</th></tr></thead>
            <tbody>
            @foreach($p['items']->sortByDesc(fn ($i) => $i['assignment']->due_at) as $i)
                @php($a = $i['assignment'])
                @php($sub = $i['submission'])
                <tr>
                    <td>@if($assignmentUrl)<a href="{{ $assignmentUrl($a) }}" class="fw-semibold text-decoration-none">{{ $a->title }}</a>@else<span class="fw-semibold">{{ $a->title }}</span>@endif
                        <div class="text-body-secondary">{{ $a->kindLabel() }}</div></td>
                    <td>{{ $a->subject?->displayName() ?? '—' }}</td>
                    <td>{{ $a->due_at->format('d M Y H:i') }}</td>
                    <td><span class="badge text-bg-{{ ['evaluated' => 'success', 'submitted' => 'primary', 'returned' => 'warning', 'missing' => 'danger', 'pending' => 'secondary'][$i['state']] }}">{{ __(ucfirst($i['state'])) }}</span>@if($sub?->is_late) <span class="badge text-bg-danger">{{ __('Late') }}</span>@endif</td>
                    <td class="text-end">{{ $sub?->marks !== null ? ($sub->marks + 0).' / '.($a->max_marks + 0) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </div>
</div>
@endif
