<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102831">
    <title>@yield('title') · Botija POS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="app-body">
@php
    $moduleDefinitions = collect(config('modules'));
    $currentModule = $moduleDefinitions->first(fn ($module) => collect($module['menu'])
        ->flatMap(fn ($group) => $group['items'])
        ->contains(fn ($item) => request()->routeIs($item['active'])));
@endphp
<svg class="svg-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="m3 7 9-4 9 4v10l-9 4-9-4V7Z"/><path d="m3 7 9 4 9-4M12 11v10"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></symbol>
    <symbol id="i-move" viewBox="0 0 24 24"><path d="M4 7h15m0 0-4-4m4 4-4 4M20 17H5m0 0 4-4m-4 4 4 4"/></symbol>
    <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2H3ZM17 5a3 3 0 0 1 0 6M17 14a6 6 0 0 1 4 6"/></symbol>
    <symbol id="i-customer" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2H4Z"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 2h8l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h5M8 13h7M8 17h7"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 2 8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6l8-4Z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-exit" viewBox="0 0 24 24"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M14 16l4-4-4-4M8 12h10"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-flame" viewBox="0 0 24 24"><path d="M12 22c-5 0-8-3.4-8-7.6 0-3.3 2-5.9 4.7-8.5-.1 3 1.6 4.3 1.6 4.3C11 6.8 13.5 4.2 15 2c.4 3.8 5 6.7 5 12.4 0 4.2-3 7.6-8 7.6Z"/></symbol>
</svg>
<div class="app-shell">
    <aside class="sidebar" id="app-sidebar" aria-label="Menu principal">
        <a class="brand" href="{{ route('modules.index') }}" aria-label="Botija POS · módulos"><span class="brand-mark"><svg><use href="#i-flame"/></svg></span><span class="brand-copy"><strong>botija<span>POS</span></strong><small>Gestão de gás</small></span></a>
        <a class="module-picker {{ request()->routeIs('modules.*') ? 'active' : '' }}" href="{{ route('modules.index') }}" @if(request()->routeIs('modules.*')) aria-current="page" @endif>
            <span class="module-picker-icon"><svg><use href="#{{ $currentModule['icon'] ?? 'i-grid' }}"/></svg></span>
            <span class="module-picker-copy"><small>{{ $currentModule ? 'MÓDULO ATIVO' : 'ÁREAS DE TRABALHO' }}</small><strong>{{ $currentModule['name'] ?? 'Escolher módulo' }}</strong></span>
            <span class="module-picker-arrow" aria-hidden="true">⌄</span>
        </a>
        <nav class="nav" aria-label="Navegação principal">
            @foreach($currentModule['menu'] ?? [] as $group)
                @php($visibleItems = collect($group['items'])->filter(fn ($item) => collect($item['permissions'] ?? [$item['permission']])->contains(fn ($permission) => auth()->user()->hasPermission($permission))))
                @if($visibleItems->isNotEmpty())
                    <span class="nav-caption">{{ $group['label'] }}</span>
                    @foreach($visibleItems as $item)
                        <a class="{{ request()->routeIs($item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}" @if(request()->routeIs($item['active'])) aria-current="page" @endif><svg><use href="#{{ $item['icon'] }}"/></svg><span>{{ $item['label'] }}</span>@if(isset($item['badge']))<span class="nav-shortcut">{{ $item['badge'] }}</span>@endif</a>
                    @endforeach
                @endif
            @endforeach
            <span class="nav-caption">CONTA</span>
            <a class="{{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif><svg><use href="#i-users"/></svg><span>Meu perfil</span></a>
        </nav>
        <div class="sidebar-footer"><span class="online-dot"></span><span>Sistema operacional</span><small>BOTIJA POS</small></div>
    </aside>
    <button class="sidebar-scrim" type="button" data-sidebar-scrim aria-label="Fechar menu"></button>
    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left"><button class="menu-toggle" type="button" data-menu-toggle aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false"><svg><use href="#i-menu"/></svg></button><div class="topbar-heading"><span>BOTIJA POS <span class="topbar-separator">/</span> @if($currentModule) {{ $currentModule['name'] }} <span class="topbar-separator">/</span> @endif @yield('title')</span><strong>@yield('title')</strong></div></div>
            <div class="topbar-right"><span class="topbar-date">{{ now()->format('d/m/Y') }}</span><a class="topbar-user" href="{{ route('profile.show') }}" title="Ver meu perfil"><span class="user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="user-details"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->salesLocation?->name ?? (auth()->user()->is_super_admin ? 'Administrador' : 'Equipa') }}</small></span></a><form class="logout-form" method="post" action="{{ route('logout') }}">@csrf<button type="submit" title="Terminar sessão" aria-label="Sair"><svg><use href="#i-exit"/></svg></button></form></div>
        </header>
        <main class="wrap" id="main-content">
            @if(session('success'))<div class="alert" role="status"><span class="alert-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
            @if($errors->any())<div class="alert error" role="alert"><span class="alert-symbol">!</span><div><strong>Verifique os dados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
            @yield('content')
        </main>
    </div>
</div>
<script>
const menuToggle = document.querySelector('[data-menu-toggle]');
const sidebarScrim = document.querySelector('[data-sidebar-scrim]');
function closeSidebar() { document.body.classList.remove('sidebar-open'); menuToggle?.setAttribute('aria-expanded', 'false'); }
menuToggle?.addEventListener('click', () => { const open = document.body.classList.toggle('sidebar-open'); menuToggle.setAttribute('aria-expanded', String(open)); });
sidebarScrim?.addEventListener('click', closeSidebar);
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeSidebar(); });
</script>
@stack('scripts')
</body>
</html>
