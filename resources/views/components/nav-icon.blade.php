@props(['name'])

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('home')
            <path d="M3 10.8 12 3l9 7.8" />
            <path d="M5.5 9.5V21h13V9.5M9 21v-7h6v7" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" />
            @break
        @case('schedule')
            <rect x="4" y="3" width="16" height="18" rx="2" />
            <path d="M8 7h8M8 11h8M8 15h4M15 17l1.5 1.5L20 15" />
            @break
        @case('swap')
            <path d="M7 7h11l-3-3M18 7l-3 3M17 17H6l3 3M6 17l3-3" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('building')
            <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M8 21v-2h4v2M2 21h20" />
            @break
        @case('tag')
            <path d="M20.6 13.6 11 23l-9-9V3h11l7.6 7.6a2.1 2.1 0 0 1 0 3Z" transform="scale(.9) translate(1.3 0)" />
            <circle cx="7.5" cy="8" r="1.2" />
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-6" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
        @case('collapse')
            <path d="m14 7-5 5 5 5" />
            <rect x="3" y="3" width="18" height="18" rx="2" />
            @break
        @case('help')
            <circle cx="12" cy="12" r="9" />
            <path d="M9.7 9a2.5 2.5 0 1 1 3.6 2.25c-.8.4-1.3 1-1.3 1.75M12 17h.01" />
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
            <path d="M10 21h4" />
            @break
        @case('sparkles')
            <path d="m12 3 1.2 3.3L16.5 7.5l-3.3 1.2L12 12l-1.2-3.3-3.3-1.2 3.3-1.2L12 3ZM18 13l.8 2.2L21 16l-2.2.8L18 19l-.8-2.2L15 16l2.2-.8L18 13ZM6 14l.7 1.8 1.8.7-1.8.7L6 19l-.7-1.8-1.8-.7 1.8-.7L6 14Z" />
            @break
        @case('install')
            <path d="M12 3v12M7.5 10.5 12 15l4.5-4.5M5 20h14" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m8 12 2.7 2.7L16.5 9" />
            @break
        @case('alert-triangle')
            <path d="M10.3 3.8 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z" />
            <path d="M12 9v4M12 17h.01" />
            @break
        @case('report')
            <path d="M5 20V10M12 20V4M19 20v-7" />
            <path d="M3 20h18" />
            @break
    @endswitch
</svg>
