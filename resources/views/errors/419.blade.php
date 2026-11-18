@extends('layouts.guest')
@section('title', 'خطا 419')
@section('content')
<div class="card login-card shadow-lg border-0"><div class="card-body p-5 text-center">
    <div class="login-brand">419</div>
    <p class="mt-3">نشست شما منقضی شده است. صفحه را بازخوانی و دوباره تلاش کنید.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">بازگشت</a>
</div></div>
@endsection
