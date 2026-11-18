@extends('layouts.app')
@section('title', $title ?? 'مستندات')
@section('content')
<div class="row g-3">
    <div class="col-lg-3"><div class="list-group">
        @foreach ($docs as $slug => [$file, $t])<a href="{{ route('admin.docs.show', $slug) }}" class="list-group-item list-group-item-action {{ request()->is('admin/docs/'.$slug) ? 'active' : '' }}">{{ $t }}</a>@endforeach
    </div></div>
    <div class="col-lg-9"><div class="card"><div class="card-body doc-body">
        @if ($html) {!! $html !!} @else <p class="text-muted">یکی از مستندات را انتخاب کنید. مستندات از پوشه docs/ همین نسخه خوانده می‌شوند.</p> @endif
    </div></div></div>
</div>
@endsection
