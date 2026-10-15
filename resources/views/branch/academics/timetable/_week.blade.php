{{-- Read-only weekly grid (days × periods) for a teacher's or a room's routine. $mode: teacher | room --}}
@php($classPeriods = $periods->reject->is_break)
@if($classPeriods->isEmpty())
    <x-empty-state icon="clock" :title="__('No periods yet')" />
@else
    <div class="table-responsive">
        <table class="table table-bordered align-top mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="min-width:90px">{{ __('Day') }}</th>
                    @foreach($periods as $p)
                        <th class="text-center" @if($p->is_break) style="width:40px" @endif>
                            <div>{{ $p->name }}</div><div class="fw-normal text-body-secondary">{{ $p->timeRange() }}</div>
                            @if($periods->pluck('shift')->unique()->count() > 1)<div class="fw-normal text-body-tertiary">{{ __(ucfirst($p->shift)) }}</div>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach($days as $day)
                <tr>
                    <th>{{ App\Models\Academic\TimetableEntry::dayName($day) }}</th>
                    @foreach($periods as $p)
                        @if($p->is_break)
                            <td class="bg-body-tertiary"></td>
                            @continue
                        @endif
                        @php($e = $grid->get($day.'-'.$p->id))
                        <td @class(['bg-primary-subtle' => $e])>
                            @if($e)
                                <div class="fw-semibold">{{ $e->subject?->name ?? $e->note }}</div>
                                <div>{{ $e->section?->label() }}</div>
                                @if($mode === 'teacher' && $e->room)<div class="text-body-secondary"><i class="bi bi-door-open"></i> {{ $e->room->name }}</div>@endif
                                @if($mode === 'room' && $e->teacher)<div class="text-body-secondary"><i class="bi bi-person"></i> {{ $e->teacher->name }}</div>@endif
                            @else
                                <span class="text-body-tertiary">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent small text-body-secondary">
        {{ trans_choice(':count class per week|:count classes per week', $grid->count(), ['count' => $grid->count()]) }}
    </div>
@endif
