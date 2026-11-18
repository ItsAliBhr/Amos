@extends('layouts.app')
@section('title', 'اعلان‌ها')
@section('content')
<form method="POST" action="{{ route('notifications.readAll') }}" class="mb-3">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">علامت‌گذاری همه به‌عنوان خوانده‌شده</button></form>
<div class="card"><ul class="list-group list-group-flush">
    @forelse ($notifications as $n)
        <li class="list-group-item {{ $n->read_at ? '' : 'bg-light' }}">
            <a href="{{ route('notifications.open', $n) }}" class="text-reset text-decoration-none d-flex justify-content-between">
                <span>@unless($n->read_at)<span class="badge text-bg-primary me-1">جدید</span>@endunless <strong>{{ $n->title }}</strong> <span class="small text-muted">{{ $n->body }}</span></span>
                <span class="small text-muted text-nowrap">{{ jdate($n->created_at, true) }}</span>
            </a>
        </li>
    @empty <li class="list-group-item empty-state">اعلانی ندارید.</li>
    @endforelse
</ul><div class="card-footer bg-white">{{ $notifications->links() }}</div></div>
@endsection
