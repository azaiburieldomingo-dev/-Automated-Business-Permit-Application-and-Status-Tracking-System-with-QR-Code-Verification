@props([
    'value' => 0,
    'label' => '',
    'icon' => null,
    'accent' => 'default', // default | green | amber | red | mint
])

<div class="stat-card accent-{{ $accent }}">
    <div class="stat-body">
        <div class="value">{!! $value !!}</div>
        <div class="label">{{ $label }}</div>
    </div>
</div>
