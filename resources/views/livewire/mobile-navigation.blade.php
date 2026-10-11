@php $personalAnchors = ! $isManager && $routeName === 'professional.dashboard'; @endphp
<div class="lg:hidden" x-data="mobileNavigation({ group: @js($activeGroup), professional: @js(! $isManager) })" @close-mobile-menu="menuOpen = false">
    <nav wire:poll.30s.visible class="mobile-bottom-nav" aria-label="Navegação móvel principal" x-show="!keyboardOpen">
        <a href="{{ $personalAnchors ? '#professional-home' : route($isManager ? 'manager.dashboard' : 'professional.dashboard') }}" @unless ($personalAnchors) wire:navigate @endunless class="mobile-bottom-item" :class="{ 'mobile-bottom-item-active': isActive('home') }" :aria-current="isActive('home') ? 'page' : null">
            <x-nav-icon name="home" class="size-5" /><span>Início</span>
        </a>
        <a href="{{ $personalAnchors ? '#schedule-title' : ($isManager ? route('manager.calendar') : route('professional.dashboard').'#schedule-title') }}" @unless ($personalAnchors) wire:navigate @endunless class="mobile-bottom-item" :class="{ 'mobile-bottom-item-active': isActive('{{ $isManager ? 'calendar' : 'schedule' }}') }" :aria-current="isActive('{{ $isManager ? 'calendar' : 'schedule' }}') ? 'page' : null">
            <x-nav-icon :name="$isManager ? 'calendar' : 'schedule'" class="size-5" /><span>{{ $isManager ? 'Calendário' : 'Escala' }}</span>
        </a>
        <a href="{{ $personalAnchors ? '#my-swaps-title' : ($isManager ? route('manager.swaps') : route('professional.dashboard').'#my-swaps-title') }}" @unless ($personalAnchors) wire:navigate @endunless class="mobile-bottom-item" :class="{ 'mobile-bottom-item-active': isActive('swaps') }" :aria-current="isActive('swaps') ? 'page' : null">
            <span class="relative"><x-nav-icon name="swap" class="size-5" />@if ($pendingCount)<span class="mobile-nav-count" aria-hidden="true">{{ min($pendingCount, 99) }}</span>@endif</span>
            <span>Trocas</span>@if ($pendingCount)<span class="sr-only">{{ $pendingCount }} pedido(s) aguardando {{ $isManager ? 'decisão' : 'sua resposta' }}</span>@endif
        </a>
        <button type="button" @click="menuOpen = true" class="mobile-bottom-item" :class="{ 'mobile-bottom-item-active': isActive('menu') || menuOpen }" :aria-current="isActive('menu') ? 'page' : null"
            aria-label="Abrir menu principal" :aria-expanded="menuOpen.toString()" aria-controls="mobile-menu" aria-describedby="mobile-menu-unread">
            <span class="relative"><x-nav-icon name="menu" class="size-5" />@if ($unreadCount)<span class="mobile-nav-count" aria-hidden="true">{{ min($unreadCount, 99) }}</span>@endif</span><span>Menu</span>
        </button>
        <span id="mobile-menu-unread" class="sr-only">{{ $unreadCount }} notificação(ões) não lida(s)</span>
    </nav>

    <x-action-modal :show="true" id="mobile-menu" title="Menu" :eyebrow="auth()->user()->role->label()" client-state="menuOpen" close-event="close-mobile-menu" initial-focus="[data-mobile-menu-link]" :restore-focus="true">
        <p class="text-sm text-muted">{{ auth()->user()->name }}</p>
        <nav class="mobile-menu-links mt-4" aria-label="Outros módulos">
            @if ($isManager)
                @can('viewAny', \App\Models\Shift::class)<a data-mobile-menu-link href="{{ route('manager.shifts') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'manager.shifts')) aria-current="page" @endif><x-nav-icon name="schedule" />Escalas</a>@endcan
                <a data-mobile-menu-link href="{{ route('manager.reports') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'manager.reports')) aria-current="page" @endif><x-nav-icon name="report" />Relatórios</a>
                @can('viewAny', \App\Models\OperationalAuditEvent::class)<a data-mobile-menu-link href="{{ route('manager.audit') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'manager.audit')) aria-current="page" @endif><x-nav-icon name="report" />Histórico operacional</a>@endcan
                @can('viewAny', \App\Models\Sector::class)<a data-mobile-menu-link href="{{ route('manager.sectors') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'manager.sectors')) aria-current="page" @endif><x-nav-icon name="building" />Setores</a>@endcan
                @can('viewAny', \App\Models\Category::class)<a data-mobile-menu-link href="{{ route('manager.categories') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'manager.categories')) aria-current="page" @endif><x-nav-icon name="tag" />Categorias</a>@endcan
                @can('viewAny', \App\Models\User::class)<a data-mobile-menu-link href="{{ route('manager.users') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'manager.users')) aria-current="page" @endif><x-nav-icon name="users" />Usuários</a>@endcan
                @can('viewAny', \App\Models\IssueReport::class)<a data-mobile-menu-link href="{{ route('manager.issue-reports') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'manager.issue-reports')) aria-current="page" @endif><x-nav-icon name="flag" />Relatos</a>@endcan
            @else
                <a data-mobile-menu-link href="{{ route('professional.calendar') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'professional.calendar')) aria-current="page" @endif><x-nav-icon name="calendar" />Calendário</a>
                <a data-mobile-menu-link href="{{ route('professional.reports') }}" wire:navigate @click="menuOpen = false" @if (str_starts_with($routeName, 'professional.reports')) aria-current="page" @endif><x-nav-icon name="report" />Relatórios</a>
            @endif
            <a data-mobile-menu-link href="{{ route('notifications') }}" wire:navigate @click="menuOpen = false" @if ($routeName === 'notifications') aria-current="page" @endif><x-nav-icon name="bell" />Notificações @if ($unreadCount)<span class="notification-count ml-auto">{{ $unreadCount }}</span>@endif</a>
            <a data-mobile-menu-link href="{{ route('help') }}" wire:navigate @click="menuOpen = false" @if ($routeName === 'help') aria-current="page" @endif><x-nav-icon name="help" />Ajuda e versões</a>
            <button type="button" data-pwa-install hidden><x-nav-icon name="install" />Instalar aplicativo</button>
        </nav>
        <p class="mt-4 text-xs text-muted">Versão {{ config('version.current') }}</p>
        <x-slot:actions>
            <form method="POST" action="{{ route('logout') }}">@csrf<x-action-button type="submit" icon="logout" class="w-full">Sair</x-action-button></form>
            <x-action-button @click="requestClose()" class="w-full">Fechar Menu</x-action-button>
        </x-slot:actions>
    </x-action-modal>
</div>
