@extends('layouts.guest')
@section('title', 'ورود')
@section('content')
<div class="card login-card shadow-lg border-0">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="login-brand">AMOS</div>
            <div class="text-muted small">سامانه مدیریت صندوق دوستانه سرمایه‌گذاری</div>
        </div>
        @include('partials.flash')
        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">نام کاربری</label>
                <input id="username" name="username" type="text" class="form-control ltr" value="{{ old('username') }}" required autofocus autocomplete="username" maxlength="64">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">رمز عبور</label>
                <div class="input-group">
                    <input id="password" name="password" type="password" class="form-control ltr" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="نمایش/مخفی‌سازی رمز"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label small" for="remember">مرا به خاطر بسپار</label>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">ورود</button>
        </form>
        <p class="text-center text-muted small mt-4 mb-0">حساب کاربری فقط توسط مدیر صندوق ایجاد می‌شود.</p>
    </div>
</div>
@endsection
