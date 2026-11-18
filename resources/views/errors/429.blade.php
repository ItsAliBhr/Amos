@extends('layouts.guest')
@section('title', 'خطا 429')
@section('content')
<div class="card login-card shadow-lg border-0"><div class="card-body p-5 text-center">
    <div class="login-brand">429</div>
    <p class="mt-3">تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">بازگشت</a>
</div></div>
@endsection
