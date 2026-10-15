@php
    $__sub = (! empty($user) && ! $user->isPlatformOwner()) ? app(\App\Services\Saas\SubscriptionService::class)->of(tenant()->institution()) : null;
    $__phase = $__sub?->phase();
    $__left = $__sub?->daysLeft();
@endphp
@if($__sub && (in_array($__phase, ['grace', 'expired', 'suspended'], true) || ($__left !== null && $__left <= 7)))
    @php($__tone = in_array($__phase, ['expired', 'suspended'], true) ? 'danger' : 'warning')
    <div class="alert alert-{{ $__tone }} d-flex gap-2 align-items-start" role="alert">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div>
            @switch($__phase)
                @case('trial') {{ __('Your free trial ends in :n day(s). Contact the platform administrator to choose a plan.', ['n' => max($__left, 0)]) }} @break
                @case('active') {{ __('Your subscription ends in :n day(s). Please renew to avoid interruption.', ['n' => max($__left, 0)]) }} @break
                @case('grace') {{ __('Your subscription has ended. Everything still works for a few days; please renew now.') }} @break
                @case('suspended') {{ __('This subscription is suspended: you can view data but not change it.') }} @break
                @default {{ __('The subscription has expired: you can view data but not change it until it is renewed.') }}
            @endswitch
        </div>
    </div>
@endif
