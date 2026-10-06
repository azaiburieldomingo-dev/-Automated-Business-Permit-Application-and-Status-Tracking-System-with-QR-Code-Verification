@props(['count' => 0])

@php
    $u = auth()->user();
    $home = $u && $u->isAdmin() ? '/admin/dashboard' : ($u && $u->isBplo() ? '/bplo/dashboard' : '/dashboard');
@endphp

<a href="{{ url($home) }}" class="bell" title="Notifications">
    &#128276;
    @if ($count > 0)
        <span class="count">{{ $count }}</span>
    @endif
</a>
