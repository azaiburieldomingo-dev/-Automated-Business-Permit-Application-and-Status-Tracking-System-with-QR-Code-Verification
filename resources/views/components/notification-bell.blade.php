@props(['count' => 0])

@php
    $u = auth()->user();
    $home = $u ? $u->homePath() : '/dashboard';
@endphp

<a href="{{ url($home) }}" class="bell" title="Notifications">
    &#128276;
    @if ($count > 0)
        <span class="count">{{ $count }}</span>
    @endif
</a>
