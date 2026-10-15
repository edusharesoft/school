@php($tone = ['trial' => 'info', 'active' => 'success', 'grace' => 'warning', 'expired' => 'danger', 'suspended' => 'dark'][$phase] ?? 'secondary')
<span class="badge text-bg-{{ $tone }}">{{ ['trial' => __('Trial'), 'active' => __('Active'), 'grace' => __('Grace period'), 'expired' => __('Expired'), 'suspended' => __('Suspended')][$phase] ?? $phase }}</span>
