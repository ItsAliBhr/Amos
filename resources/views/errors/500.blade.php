@extends('layouts.guest')
@section('title', 'خطا 500')
@section('content')
<div class="card login-card shadow-lg border-0"><div class="card-body p-5 text-center">
    <div class="login-brand">500</div>
    <p class="mt-3">خطای داخلی رخ داد. لطفاً بعداً تلاش کنید یا با مدیر تماس بگیرید.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">بازگشت</a>
</div></div>
@endsection
