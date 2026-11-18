@extends('layouts.guest')
@section('title', 'خطا 403')
@section('content')
<div class="card login-card shadow-lg border-0"><div class="card-body p-5 text-center">
    <div class="login-brand">403</div>
    <p class="mt-3">شما مجوز دسترسی به این صفحه را ندارید.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">بازگشت</a>
</div></div>
@endsection
