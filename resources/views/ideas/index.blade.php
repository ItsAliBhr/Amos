@extends('layouts.app')
@section('title', 'ایده‌های سرمایه‌گذاری')
@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <a href="{{ route('ideas.create') }}" class="btn btn-primary"><i class="bi bi-lightbulb"></i> ثبت ایده جدید</a>
    <form class="d-flex gap-2" method="GET">
        <input name="q" class="form-control form-control-sm" placeholder="جست‌وجوی عنوان" value="{{ request('q') }}">
        <select name="status" class="form-select form-select-sm"><option value="">همه وضعیت‌ها</option>@foreach (\App\Models\Idea::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $v }}</option>@endforeach</select>
        <div class="form-check align-self-center text-nowrap"><input type="checkbox" class="form-check-input" name="mine" value="1" id="mine" @checked(request('mine'))><label for="mine" class="form-check-label small">ایده‌های من</label></div>
        <button class="btn btn-sm btn-outline-primary">فیلتر</button>
    </form>
</div>
<div class="row g-3">
@forelse ($ideas as $idea)
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="h6 fw-bold mb-0"><a href="{{ route('ideas.show', $idea) }}" class="text-reset">{{ $idea->title }}</a></h3>
                    {!! status_badge($idea->status) !!}
                </div>
                <div class="small text-muted mb-2">{{ $idea->proposer?->name }} · {{ jdate($idea->created_at) }} @if($idea->category) · {{ $idea->category->name }}@endif</div>
                <p class="small mb-2">{{ \Illuminate\Support\Str::limit($idea->description, 140) }}</p>
                <div class="small d-flex gap-3">
                    @if ($idea->required_capital_irr)<span>سرمایه: <span class="money">{{ money($idea->required_capital_irr) }}</span></span>@endif
                    @if ($idea->expected_return_min !== null)<span>بازده: <span class="num">{{ num($idea->expected_return_min, 2) }}{{ $idea->expected_return_max !== null ? '–'.num($idea->expected_return_max, 2) : '' }}٪</span></span>@endif
                </div>
            </div>
            <div class="card-footer bg-white small d-flex gap-3">
                <span class="text-success"><i class="bi bi-hand-thumbs-up"></i> {{ $idea->agree_count }}</span>
                <span class="text-danger"><i class="bi bi-hand-thumbs-down"></i> {{ $idea->disagree_count }}</span>
                <span class="text-secondary"><i class="bi bi-dash-circle"></i> {{ $idea->abstain_count }}</span>
            </div>
        </div>
    </div>
@empty
    <div class="col-12"><div class="card card-body empty-state">ایده‌ای یافت نشد.</div></div>
@endforelse
</div>
<div class="mt-3">{{ $ideas->links() }}</div>
@endsection
