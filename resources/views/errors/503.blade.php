@extends('layouts.guest')
@section('title', 'خطا 503')
@section('content')
<div class="card login-card shadow-lg border-0"><div class="card-body p-5 text-center">
    <div class="login-brand">503</div>
    <p class="mt-3">سامانه در حال به‌روزرسانی است. کمی بعد مراجعه کنید.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">بازگشت</a>
</div></div>
@endsection
