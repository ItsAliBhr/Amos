@extends('layouts.base')

@php
    $u = auth()->user();
    $nav = [
        ['dashboard', 'داشبورد', 'speedometer2', 'dashboard'],
        ['contributions.index', 'واریزی‌ها', 'cash-coin', 'contributions.*'],
        ['investments.index', 'سرمایه‌گذاری‌ها', 'graph-up-arrow', 'investments.*'],
        ['assets.index', 'دارایی‌ها و ارزش‌گذاری', 'gem', 'assets.*|valuations.*'],
        ['ideas.index', 'ایده‌های سرمایه‌گذاری', 'lightbulb', 'ideas.*'],
        ['withdrawals.index', 'درخواست برداشت', 'box-arrow-up-right', 'withdrawals.*'],
        ['reports.index', 'گزارش‌ها', 'file-earmark-bar-graph', 'reports.*'],
        ['profile.show', 'پروفایل من', 'person-circle', 'profile.*|password.*'],
    ];
    $adminNav = [
        ['admin.approvals', 'صف تأییدها', 'check2-square', 'admin.approvals'],
        ['admin.users.index', 'مدیریت کاربران', 'people', 'admin.users.*'],
        ['admin.settings.edit', 'اطلاعات پایه و تنظیمات', 'sliders', 'admin.settings.*|admin.categories.*|admin.cash.*|admin.assets.*'],
        ['admin.ledger.index', 'دفتر کل', 'journal-text', 'admin.ledger.*'],
        ['admin.audit.index', 'Audit Log', 'shield-check', 'admin.audit.*'],
        ['admin.docs.index', 'مدیریت مستندات', 'book', 'admin.docs.*'],
    ];
    $fundVisible = $u->isAdmin() || \App\Models\FundSetting::current()->member_dashboard_visibility === 'full';
    if (! $fundVisible) {
        $nav = array_values(array_filter($nav, fn ($i) => ! in_array($i[0], ['investments.index', 'assets.index'], true)));
    }
    $isActive = fn ($patterns) => collect(explode('|', $patterns))->contains(fn ($p) => request()->routeIs($p));
@endphp

@section('body')
<div class="d-flex amos-shell">
    <aside class="amos-sidebar offcanvas-lg offcanvas-end p-3" tabindex="-1" id="sidebar">
        <div class="d-flex align-items-center justify-content-between mb-3 px-2">
            <div>
                <div class="brand">AMOS</div>
                <div class="small text-truncate" style="max-width:180px">{{ $fundName }}</div>
            </div>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="بستن"></button>
        </div>
        <nav class="nav flex-column gap-1">
            @foreach ($nav as [$route, $label, $icon, $pattern])
                <a class="nav-link {{ $isActive($pattern) ? 'active' : '' }}" href="{{ route($route) }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
            @endforeach
            @if ($u->isAdmin())
                <div class="nav-section">مدیریت</div>
                @foreach ($adminNav as [$route, $label, $icon, $pattern])
                    <a class="nav-link {{ $isActive($pattern) ? 'active' : '' }}" href="{{ route($route) }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
                @endforeach
            @endif
        </nav>
    </aside>

    <div class="flex-grow-1 amos-main d-flex flex-column">
        <header class="amos-topbar px-3 py-2 d-flex align-items-center gap-2 sticky-top">
            <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="منو"><i class="bi bi-list"></i></button>
            <h1 class="h6 mb-0 fw-bold flex-grow-1 text-truncate">@yield('title')</h1>
            <a href="{{ route('notifications.index') }}" class="btn btn-light btn-sm position-relative" title="اعلان‌ها">
                <i class="bi bi-bell"></i>
                @if ($unreadCount > 0)
                    <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
            </a>
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person"></i> <span class="d-none d-sm-inline">{{ $u->name }}</span></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted">{{ \App\Models\User::ROLES[$u->role] ?? $u->role }} · {{ $u->username }}</span></li>
                    <li><a class="dropdown-item" href="{{ route('profile.show') }}">پروفایل من</a></li>
                    <li><a class="dropdown-item" href="{{ route('password.edit') }}">تغییر رمز عبور</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-left"></i> خروج</button></form>
                    </li>
                </ul>
            </div>
        </header>
        <main class="p-3 p-md-4 flex-grow-1">
            @include('partials.flash')
            @yield('content')
        </main>
        <footer class="text-center small text-muted py-3">AMOS v{{ config('amos.version') }} — ابزار مدیریت داخلی؛ جایگزین حسابداری رسمی یا مشاوره سرمایه‌گذاری نیست.</footer>
    </div>
</div>
@endsection
