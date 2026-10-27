@props(['title' => null])
@php
    $user = auth()->user();
    $pendingCount = $user->can('approve') ? app(\App\Http\Controllers\ApprovalController::class)->pendingItems()->count() : 0;
    $activeOrders = $user->can('orders.view') ? \App\Models\WorkOrder::query()->whereIn('stage', ['printing', 'folding', 'binding', 'ready'])->count() : 0;
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'dash', 'label' => __('Dashboard')],
        ['group' => __('Control')],
        ['route' => 'paper.index', 'match' => ['paper*', 'godowns.*'], 'icon' => 'paper', 'label' => __('Paper stock'), 'can' => 'paper.view'],
        ['route' => 'inventory.index', 'match' => ['inventory*', 'purchases.*'], 'icon' => 'box', 'label' => __('Inventory'), 'can' => 'purchases.view'],
        ['route' => 'expenses.index', 'match' => ['expenses.*', 'expense-heads.*'], 'icon' => 'wallet', 'label' => __('Cash-out'), 'can' => 'expenses.view'],
        ['route' => 'machines.index', 'match' => 'machine*', 'icon' => 'wrench', 'label' => __('Machines'), 'can' => 'machines.view'],
        ['route' => 'approvals.index', 'match' => 'approvals.*', 'icon' => 'check', 'label' => __('Approvals'), 'count' => $pendingCount, 'can' => 'approve'],
        ['route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'chart', 'label' => __('Reports'), 'can' => 'reports.view'],
        ['group' => __('Production')],
        ['route' => 'work-orders.index', 'match' => 'work-orders.*', 'icon' => 'orders', 'label' => __('Work orders'), 'can' => 'orders.view', 'count' => $activeOrders],
        ['route' => 'books.index', 'match' => 'books.*', 'icon' => 'book', 'label' => __('Books & formats'), 'can' => 'books.view'],
        ['route' => 'contractors.index', 'match' => ['contractors.*', 'contractor-jobs.*'], 'icon' => 'fold', 'label' => __('Folding & binding'), 'can' => 'orders.view'],
        ['route' => 'plate-jobs.index', 'match' => 'plate-jobs.*', 'icon' => 'file', 'label' => __('Plates'), 'can' => 'orders.view'],
        ['route' => 'dispatches.index', 'match' => 'dispatches.*', 'icon' => 'truck', 'label' => __('Loading & dispatch'), 'can' => 'orders.view'],
        ['route' => 'buyers.index', 'match' => 'buyers.*', 'icon' => 'users', 'label' => __('Buyers'), 'can' => 'orders.view'],
        ['route' => 'waste.index', 'match' => 'waste.*', 'icon' => 'drop', 'label' => __('Waste'), 'can' => 'orders.view'],
        ['group' => __('People')],
        ['route' => 'employees.index', 'match' => ['employees.*', 'designations.*'], 'icon' => 'users', 'label' => __('Staff'), 'can' => 'staff.view'],
        ['route' => 'attendance.index', 'match' => 'attendance.*', 'icon' => 'fingerprint', 'label' => __('Attendance'), 'can' => 'staff.view'],
        ['route' => 'salary-advances.index', 'match' => ['salary-advances.*', 'foreman-sheets.*', 'loans.*'], 'icon' => 'wallet', 'label' => __('Advances & loans'), 'can' => 'staff.view'],
        ['route' => 'payroll.index', 'match' => 'payroll.*', 'icon' => 'cal', 'label' => __('Payroll'), 'can' => 'staff.view'],
        ['group' => __('Records')],
        ['route' => 'parties.index', 'match' => 'parties.*', 'icon' => 'users', 'label' => __('Parties & suppliers'), 'can' => 'parties.manage'],
        ['route' => 'users.index', 'match' => ['users.*', 'roles.*'], 'icon' => 'shield', 'label' => __('Users & roles'), 'can' => 'users.manage'],
    ];
    $nav = array_values(array_filter($nav, fn ($item) => ! isset($item['can']) || $user->can($item['can'])));
    $nav = array_values(array_filter($nav, fn ($item, $i) => ! isset($item['group']) || (isset($nav[$i + 1]) && ! isset($nav[$i + 1]['group'])), ARRAY_FILTER_USE_BOTH));
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Etihad ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Noto+Nastaliq+Urdu:wght@400;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
<div class="app" id="app">
    <script>try { if (localStorage.getItem('sidebar') === 'collapsed' && innerWidth > 900) document.getElementById('app').classList.add('collapsed'); } catch (e) {}</script>
    <aside class="side" id="side">
        <button type="button" class="side-close" id="sideClose" aria-label="{{ __('Close menu') }}"><x-icon name="x" /></button>
        <div class="side-top">
            <svg class="swoosh" viewBox="0 0 256 110" preserveAspectRatio="none"><path d="M0 92 C 90 70, 170 108, 256 64 L256 0 L0 0Z" fill="#2E3490"/><path d="M0 98 C 90 76, 170 114, 256 70" stroke="#3E6FC1" stroke-width="3" fill="none"/></svg>
            <div class="brand"><x-mark :size="46" /><div class="brand-name"><b>ETIHAD</b><span>Publishers &amp; Printers</span></div></div>
        </div>
        <nav class="nav" aria-label="{{ __('Main') }}">
            @foreach ($nav as $item)
                @if (isset($item['group']))
                    <div class="nav-group">{{ $item['group'] }}</div>
                @elseif (isset($item['soon']))
                    <a class="soon" aria-disabled="true" title="{{ $item['label'] }}"><x-icon :name="$item['icon']" /><span>{{ $item['label'] }}</span><span class="soon-tag">{{ $item['soon'] }}</span></a>
                @else
                    @php $on = request()->routeIs($item['match']); @endphp
                    <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}" @class(['on' => $on]) @if ($on) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" /><span>{{ $item['label'] }}</span>
                        @if (! empty($item['count']))<span class="count">{{ $item['count'] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </nav>
    </aside>
    <div class="scrim-side" id="scrimSide"></div>
    <div style="min-width:0;display:flex;flex-direction:column">
        <header class="topbar">
            <button class="icon-btn menu-btn" id="menuBtn" type="button" aria-label="{{ __('Menu') }}" aria-controls="side" aria-expanded="true"><x-icon name="menu" /></button>
            <div class="crumbs"><span class="hide-s">Etihad ERP</span><span class="hide-s"><x-icon name="chev" /></span><b>{{ $title }}</b></div>
            <div style="margin-inline-start:auto"></div>
            <div class="lang" role="group" aria-label="{{ __('Language') }}">
                <a href="{{ route('locale', 'en') }}" @class(['on' => app()->getLocale() === 'en'])>EN</a>
                <a href="{{ route('locale', 'ur') }}" @class(['ur', 'on' => app()->getLocale() === 'ur'])>اردو</a>
            </div>
            @can('approve')
                <a href="{{ route('approvals.index') }}" class="icon-btn" aria-label="{{ __('Approvals') }}"><x-icon name="bell" />@if ($pendingCount)<span class="dot"></span>@endif</a>
            @endcan
            @can('manage-settings')
                <a href="{{ route('settings.edit') }}" @class(['icon-btn', 'on' => request()->routeIs('settings.*')]) aria-label="{{ __('Settings') }}" title="{{ __('Settings') }}"><x-icon name="cog" /></a>
            @endcan
            <div class="rel">
                <button type="button" class="account-btn" id="accountBtn" aria-haspopup="true" aria-expanded="false" aria-controls="accountMenu">
                    <span class="avatar sm">{{ $user->initials() }}</span>
                    <span class="account-name"><b>{{ $user->name }}</b><small>{{ $user->role?->name }}</small></span>
                    <x-icon name="chevd" />
                </button>
                <div class="pop account-menu" id="accountMenu" hidden>
                    <div class="account-head">
                        <span class="avatar">{{ $user->initials() }}</span>
                        <div style="min-width:0"><b>{{ $user->name }}</b><div class="meta">{{ $user->email }}</div><div class="meta">{{ $user->role?->name }}</div></div>
                    </div>
                    @can('manage-settings')
                        <a class="menu-item" href="{{ route('settings.edit') }}"><x-icon name="cog" />{{ __('Settings') }}</a>
                    @endcan
                    @can('users.manage')
                        <a class="menu-item" href="{{ route('users.index') }}"><x-icon name="shield" />{{ __('Users & roles') }}</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="menu-item danger"><x-icon name="logout" />{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        </header>
        <main class="content">
            @if (session('status'))
                <div class="flash" role="status"><x-icon name="check" />{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
<script>
    (() => {
        const app = document.getElementById('app'), btn = document.getElementById('menuBtn');
        const isPhone = () => matchMedia('(max-width: 900px)').matches;
        const sync = () => btn.setAttribute('aria-expanded', String(isPhone() ? app.classList.contains('open') : !app.classList.contains('collapsed')));
        const closeDrawer = () => { app.classList.remove('open'); sync(); };
        // Computer: collapse the sidebar to icons and remember it. Phone: slide the menu in.
        btn.addEventListener('click', () => {
            if (isPhone()) {
                app.classList.add('open');
                document.getElementById('sideClose').focus();
            } else {
                const collapsed = app.classList.toggle('collapsed');
                try { localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'open'); } catch (e) {}
            }
            sync();
        });
        document.getElementById('sideClose').addEventListener('click', () => { closeDrawer(); btn.focus(); });
        document.getElementById('scrimSide').addEventListener('click', closeDrawer);
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && app.classList.contains('open')) { closeDrawer(); btn.focus(); } });
        addEventListener('resize', sync);
        sync();
    })();

    /**
     * On phones each table row is shown as a card; label every cell with its column heading
     * and wrap its content so the label and value sit side by side. Safe to call again for new rows.
     */
    window.stackTables = (root = document) => {
        root.querySelectorAll('.tbl-wrap > table:not(.no-stack)').forEach(table => {
            const heads = [...table.querySelectorAll('thead th')].map(th => th.textContent.replace(/\s+/g, ' ').trim());
            table.querySelectorAll('tbody tr, tfoot tr').forEach(tr => {
                let col = 0;
                [...tr.children].forEach(td => {
                    const span = td.colSpan || 1;
                    if (!td.hasAttribute('data-label')) {
                        td.dataset.label = span > 1 ? '' : (heads[col] || '');
                    }
                    if (!td.firstElementChild?.classList.contains('cell-v') && !td.hasAttribute('colspan')) {
                        const wrap = document.createElement('div');
                        wrap.className = 'cell-v';
                        while (td.firstChild) { wrap.appendChild(td.firstChild); }
                        td.appendChild(wrap);
                    }
                    col += span;
                });
            });
            table.classList.add('stackable');
        });
    };
    stackTables();
    // A row marked .row-link opens its record when clicked anywhere outside its own links and buttons.
    document.addEventListener('click', e => {
        const row = e.target.closest('tr.row-link[data-href]');
        if (row && !e.target.closest('a, button, input, select, textarea, label') && !getSelection().toString()) {
            location.href = row.dataset.href;
        }
    });
    (() => {
        const btn = document.getElementById('accountBtn'), menu = document.getElementById('accountMenu');
        const toggle = open => { menu.hidden = !open; btn.setAttribute('aria-expanded', String(open)); };
        btn.addEventListener('click', e => { e.stopPropagation(); toggle(menu.hidden); });
        document.addEventListener('click', e => { if (!menu.hidden && !menu.contains(e.target)) { toggle(false); } });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !menu.hidden) { toggle(false); btn.focus(); } });
    })();
</script>
@stack('scripts')
</body>
</html>
