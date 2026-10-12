@props([
    'icon' => '📭',
    'title' => 'Nothing here yet',
    'message' => null,
])

<div class="empty-state">
    <p class="empty-title">{{ $title }}</p>
    @if ($message)
        <p class="muted mb-0">{{ $message }}</p>
    @endif
    @if (trim($slot))
        <div class="empty-action">{{ $slot }}</div>
    @endif
</div>
