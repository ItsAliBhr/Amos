@extends('layouts.app')
@section('title', 'پروفایل من')
@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">اطلاعات حساب</div><div class="card-body">
            <dl class="row kv">
                <div class="col-6"><dt>نام</dt><dd>{{ $user->name }}</dd></div>
                <div class="col-6"><dt>نام کاربری</dt><dd class="ltr text-end">{{ $user->username }}</dd></div>
                <div class="col-6"><dt>نقش</dt><dd>{{ \App\Models\User::ROLES[$user->role] }}</dd></div>
                <div class="col-6"><dt>تاریخ عضویت</dt><dd>{{ jdate($user->joined_at) }}</dd></div>
                <div class="col-6"><dt>تعهد ماهانه</dt><dd class="money">{{ money($user->effectiveMonthlyCommitment()) }}</dd></div>
                <div class="col-6"><dt>آخرین ورود</dt><dd>{{ jdate($user->last_login_at, true) }}</dd></div>
            </dl>
            <div class="small text-muted">تغییر نام، نقش و تعهد ماهانه فقط توسط مدیر امکان‌پذیر است.</div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">اطلاعات تماس</div><div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PUT')
                <div class="mb-3"><label class="form-label">موبایل</label><input name="mobile" class="form-control ltr" value="{{ old('mobile', $user->mobile) }}"></div>
                <div class="mb-3"><label class="form-label">ایمیل</label><input name="email" type="email" class="form-control ltr" value="{{ old('email', $user->email) }}"></div>
                <button class="btn btn-primary" type="submit">ذخیره</button>
            </form>
        </div></div>
        <a href="{{ route('password.edit') }}" class="btn btn-outline-secondary"><i class="bi bi-key"></i> تغییر رمز عبور</a>
    </div>
</div>
@endsection
