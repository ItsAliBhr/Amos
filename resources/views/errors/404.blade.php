@extends('layouts.guest')
@section('title', 'خطا 404')
@section('content')
<div class="card login-card shadow-lg border-0"><div class="card-body p-5 text-center">
    <div class="login-brand">404</div>
    <p class="mt-3">صفحه یا رکورد موردنظر یافت نشد.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">بازگشت</a>
</div></div>
@endsection
