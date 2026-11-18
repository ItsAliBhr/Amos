@extends('layouts.guest')
@section('title', 'نصب AMOS')
@section('content')
<div class="card shadow-lg border-0" style="max-width: 760px; width: 100%">
    <div class="card-body p-4">
        <div class="text-center mb-3"><div class="login-brand">AMOS</div><div class="text-muted">نصب‌کننده امن — فقط یک بار قابل اجراست</div></div>
        @include('partials.flash')
        <h2 class="h6 fw-bold">۱. بررسی پیش‌نیازها</h2>
        <ul class="list-group mb-4 small">
            @foreach ($checks as [$label, $ok])
                <li class="list-group-item d-flex justify-content-between">{{ $label }} <span class="{{ $ok ? 'text-success' : 'text-danger' }}"><i class="bi bi-{{ $ok ? 'check-circle' : 'x-circle' }}"></i></span></li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('install.store') }}" autocomplete="off">
            @csrf
            <h2 class="h6 fw-bold">۲. اطلاعات دیتابیس (ساخته‌شده در cPanel › MySQL Databases)</h2>
            <div class="row g-2 mb-4">
                <div class="col-md-8"><label class="form-label small">میزبان</label><input name="db_host" class="form-control ltr" value="{{ old('db_host', 'localhost') }}" required></div>
                <div class="col-md-4"><label class="form-label small">پورت</label><input name="db_port" type="number" class="form-control ltr" value="{{ old('db_port', 3306) }}" required></div>
                <div class="col-md-4"><label class="form-label small">نام دیتابیس</label><input name="db_database" class="form-control ltr" value="{{ old('db_database') }}" required placeholder="cpuser_amos"></div>
                <div class="col-md-4"><label class="form-label small">نام کاربری دیتابیس</label><input name="db_username" class="form-control ltr" value="{{ old('db_username') }}" required></div>
                <div class="col-md-4"><label class="form-label small">رمز دیتابیس</label><input name="db_password" type="password" class="form-control ltr" autocomplete="new-password"></div>
            </div>
            <h2 class="h6 fw-bold">۳. مشخصات سامانه و مدیر اولیه</h2>
            <div class="row g-2 mb-4">
                <div class="col-md-6"><label class="form-label small">آدرس سامانه</label><input name="app_url" class="form-control ltr" value="{{ old('app_url', $url) }}" required></div>
                <div class="col-md-6"><label class="form-label small">نام صندوق</label><input name="fund_name" class="form-control" value="{{ old('fund_name', 'صندوق دوستانه AMOS') }}" required></div>
                <div class="col-md-6"><label class="form-label small">نام مدیر</label><input name="admin_name" class="form-control" value="{{ old('admin_name') }}" required></div>
                <div class="col-md-6"><label class="form-label small">نام کاربری مدیر (لاتین)</label><input name="admin_username" class="form-control ltr" value="{{ old('admin_username') }}" required></div>
                <div class="col-md-6"><label class="form-label small">رمز مدیر</label><input name="admin_password" type="password" class="form-control ltr" required autocomplete="new-password"><div class="form-text">حداقل ۱۰ کاراکتر، حروف بزرگ و کوچک لاتین و عدد.</div></div>
                <div class="col-md-6"><label class="form-label small">تکرار رمز مدیر</label><input name="admin_password_confirmation" type="password" class="form-control ltr" required autocomplete="new-password"></div>
            </div>
            <div class="alert alert-warning small">نصب‌کننده هرگز دیتابیس دارای داده را بازنویسی نمی‌کند. پس از نصب موفق، این صفحه به‌طور خودکار قفل می‌شود (فایل <code>storage/app/installed.lock</code>).</div>
            <button class="btn btn-primary w-100 py-2" type="submit">نصب AMOS</button>
        </form>
    </div>
</div>
@endsection
