<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'SCFP'))</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* ── Sidebar ─────────────────────────────────────────────── */
            .scfp-sidebar {
                width: 240px;
                min-height: 100vh;
                background: linear-gradient(180deg, #1e1b4b 0%, #312e81 60%, #3730a3 100%);
                display: flex;
                flex-direction: column;
                position: fixed;
                top: 0; left: 0; bottom: 0;
                z-index: 40;
                transition: transform 0.25s ease;
            }
            .scfp-sidebar .brand {
                padding: 1.5rem 1.25rem 1rem;
                font-size: 1.35rem;
                font-weight: 800;
                letter-spacing: 0.05em;
                color: #fff;
                border-bottom: 1px solid rgba(255,255,255,0.10);
            }
            .scfp-sidebar .brand span {
                display: block;
                font-size: 0.63rem;
                font-weight: 400;
                letter-spacing: 0.08em;
                color: rgba(255,255,255,0.50);
                margin-top: 2px;
                text-transform: uppercase;
            }
            .scfp-nav-section {
                padding: 0.75rem 1.25rem 0.2rem;
                font-size: 0.6rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: rgba(255,255,255,0.33);
            }
            .scfp-nav-item {
                display: flex;
                align-items: center;
                gap: 0.7rem;
                padding: 0.6rem 1.1rem;
                margin: 0.08rem 0.6rem;
                border-radius: 0.45rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: rgba(255,255,255,0.72);
                text-decoration: none;
                transition: background 0.15s, color 0.15s;
                border: none;
                background: none;
                cursor: pointer;
                width: calc(100% - 1.2rem);
                text-align: left;
            }
            .scfp-nav-item:hover {
                background: rgba(255,255,255,0.10);
                color: #fff;
            }
            .scfp-nav-item.active {
                background: rgba(255,255,255,0.17);
                color: #fff;
                font-weight: 600;
            }
            .scfp-nav-item .nav-icon {
                width: 1.1rem;
                height: 1.1rem;
                opacity: 0.80;
                flex-shrink: 0;
            }
            .scfp-sidebar .sidebar-footer {
                margin-top: auto;
                padding: 0.75rem;
                border-top: 1px solid rgba(255,255,255,0.10);
            }
            .user-card {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                padding: 0.5rem 0.5rem 0.75rem;
            }
            .user-avatar {
                width: 2rem;
                height: 2rem;
                border-radius: 50%;
                background: rgba(255,255,255,0.18);
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 0.8rem;
                color: #fff;
                flex-shrink: 0;
            }
            .user-name  { font-size: 0.8rem; font-weight: 600; color: #fff; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .user-email { font-size: 0.63rem; color: rgba(255,255,255,0.45); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

            /* ── Main ────────────────────────────────────────────────── */
            .scfp-main  { margin-left: 240px; min-height: 100vh; background: #f3f4f6; display: flex; flex-direction: column; }
            .scfp-topbar {
                position: sticky; top: 0; z-index: 30;
                background: #fff;
                border-bottom: 1px solid #e5e7eb;
                height: 54px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 1.5rem;
                gap: 1rem;
            }
            .topbar-title { font-size: 0.9rem; font-weight: 600; color: #374151; }
            .topbar-actions { display: flex; align-items: center; gap: 0.6rem; }
            .btn-quick {
                display: inline-flex; align-items: center; gap: 0.3rem;
                font-size: 0.78rem; font-weight: 600;
                padding: 0.38rem 0.85rem;
                border-radius: 0.375rem;
                text-decoration: none;
                white-space: nowrap;
            }
            .btn-quick-green  { background: #059669; color: #fff; }
            .btn-quick-green:hover  { background: #047857; }
            .btn-quick-red    { background: #dc2626; color: #fff; }
            .btn-quick-red:hover    { background: #b91c1c; }
            .hamburger { background: none; border: none; cursor: pointer; display: none; padding: 0.3rem; }

            /* ── Mobile ──────────────────────────────────────────────── */
            .scfp-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 35; }
            @media (max-width: 768px) {
                .scfp-sidebar  { transform: translateX(-100%); }
                .scfp-sidebar.open { transform: translateX(0); }
                .scfp-overlay.open { display: block; }
                .scfp-main     { margin-left: 0; }
                .scfp-topbar   { padding: 0 1rem; }
                .hamburger     { display: flex; }
                .topbar-title  { display: none; }
            }
        </style>
    </head>
    <body class="font-sans antialiased" style="margin:0;padding:0;background:#f3f4f6;">

        @auth
        {{-- ═══ SIDEBAR ═══════════════════════════════════════════════ --}}
        <aside class="scfp-sidebar" id="scfp-sidebar" aria-label="Menú principal">

            <div class="brand">
                SCFP
                <span>Control Financiero Personal</span>
            </div>

            <nav style="flex:1;overflow-y:auto;padding:0.5rem 0 0.5rem;">

                {{-- ── General ── --}}
                <p class="scfp-nav-section">General</p>

                <a href="{{ route('dashboard') }}"
                   class="scfp-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('profile.show') }}"
                   class="scfp-nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Mi Perfil
                </a>

                {{-- ── Ingresos ── --}}
                <p class="scfp-nav-section" style="margin-top:0.5rem;">Ingresos</p>

                <a href="{{ route('ingresos.create') }}"
                   class="scfp-nav-item {{ request()->routeIs('ingresos.create') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Registrar ingreso
                </a>

                <a href="{{ route('ingresos.index') }}"
                   class="scfp-nav-item {{ request()->routeIs('ingresos.index') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Mis ingresos
                </a>

                <a href="{{ route('ingresos.recurrentes.index') }}"
                   class="scfp-nav-item {{ request()->routeIs('ingresos.recurrentes.*') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Recurrentes
                </a>

                <a href="{{ route('ingresos.fuentes.index') }}"
                   class="scfp-nav-item {{ request()->routeIs('ingresos.fuentes.*') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Fuentes
                </a>

                {{-- ── Egresos ── --}}
                <p class="scfp-nav-section" style="margin-top:0.5rem;">Egresos</p>

                <a href="{{ route('gastos.create') }}"
                   class="scfp-nav-item {{ request()->routeIs('gastos.create') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Registrar egreso
                </a>

                <a href="{{ route('gastos.index') }}"
                   class="scfp-nav-item {{ request()->routeIs('gastos.index') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Mis egresos
                </a>

                <a href="{{ route('gastos.recurrentes.index') }}"
                   class="scfp-nav-item {{ request()->routeIs('gastos.recurrentes.*') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Recurrentes
                </a>

                <a href="{{ route('gastos.categorias.index') }}"
                   class="scfp-nav-item {{ request()->routeIs('gastos.categorias.*') ? 'active' : '' }}">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    Categorías
                </a>

                @if (Auth::user()?->esAdmin())
                    <p class="scfp-nav-section" style="margin-top:0.5rem;">Administración</p>
                    <a href="{{ route('admin.dashboard') }}"
                       class="scfp-nav-item {{ request()->routeIs('admin.*') ? 'active' : '' }}"
                       style="color:#fca5a5;">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Panel Admin
                    </a>
                @endif
            </nav>

            {{-- Footer sidebar: usuario + logout --}}
            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <div style="min-width:0;flex:1;">
                        <div class="user-name">{{ Auth::user()->name }}</div>
                        <div class="user-email">{{ Auth::user()->email }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="scfp-nav-item" style="color:rgba(255,255,255,0.5);font-size:0.8rem;">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        {{-- Overlay para cerrar sidebar en móvil --}}
        <div class="scfp-overlay" id="scfp-overlay" onclick="closeSidebar()"></div>
        @endauth

        {{-- ═══ ÁREA PRINCIPAL ═══════════════════════════════════════ --}}
        <div class="{{ Auth::check() ? 'scfp-main' : '' }}">

            @auth
            {{-- Top bar con accesos rápidos --}}
            <div class="scfp-topbar">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <button class="hamburger" onclick="toggleSidebar()" aria-label="Abrir menú">
                        <svg width="22" height="22" fill="none" stroke="#374151" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <span class="topbar-title">@yield('title', config('app.name', 'SCFP'))</span>
                </div>
                <div class="topbar-actions">
                    <a href="{{ route('ingresos.create') }}" class="btn-quick btn-quick-green">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ingreso
                    </a>
                    <a href="{{ route('gastos.create') }}" class="btn-quick btn-quick-red">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Egreso
                    </a>
                </div>
            </div>
            @endauth

            {{-- Slot de encabezado de página (x-app-layout) --}}
            @isset($header)
                <header class="bg-white shadow-sm">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            {{-- Contenido principal: soporta $slot (x-app-layout) y @section (extends) --}}
            <main>
                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot ?? '' }}
                @endif
            </main>
        </div>

        <script>
        function toggleSidebar() {
            document.getElementById('scfp-sidebar')?.classList.toggle('open');
            document.getElementById('scfp-overlay')?.classList.toggle('open');
        }
        function closeSidebar() {
            document.getElementById('scfp-sidebar')?.classList.remove('open');
            document.getElementById('scfp-overlay')?.classList.remove('open');
        }
        </script>
    </body>
</html>
