@extends('layouts.app')
@section('title', $idea->title)
@section('content')
@php $user = auth()->user(); $reviewable = in_array($idea->status, \App\Domain\Ideas\IdeaService::REVIEWABLE, true); @endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center"><span>{{ $idea->title }}</span>{!! status_badge($idea->status) !!}</div>
            <div class="card-body">
                <div class="small text-muted mb-3">پیشنهاددهنده: {{ $idea->proposer?->name }} · {{ jdate($idea->created_at, true) }} @if($idea->category)· دسته: {{ $idea->category->name }}@endif</div>
                <p style="white-space: pre-line">{{ $idea->description }}</p>
                <dl class="row kv mb-0">
                    <div class="col-sm-4"><dt>مبلغ موردنیاز</dt><dd class="money">{{ $idea->required_capital_irr ? money($idea->required_capital_irr) : '—' }}</dd></div>
                    <div class="col-sm-4"><dt>افق زمانی</dt><dd>{{ $idea->time_horizon ?? '—' }}</dd></div>
                    <div class="col-sm-4"><dt>بازده مورد انتظار</dt><dd>{{ $idea->expected_return_min !== null ? num($idea->expected_return_min, 2).($idea->expected_return_max !== null ? ' تا '.num($idea->expected_return_max, 2) : '').'٪ '.(\App\Models\Idea::PERIODS[$idea->expected_return_period] ?? '') : '—' }}</dd></div>
                    @if ($idea->risk_description)<div class="col-12"><dt>ریسک‌ها</dt><dd style="white-space: pre-line">{{ $idea->risk_description }}</dd></div>@endif
                    @if ($idea->assumptions)<div class="col-12"><dt>فرضیات</dt><dd style="white-space: pre-line">{{ $idea->assumptions }}</dd></div>@endif
                    @if ($idea->attachment)<div class="col-12"><dt>مستندات</dt><dd><a href="{{ route('attachments.show', $idea->attachment) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> مشاهده</a></dd></div>@endif
                </dl>
                @if ($idea->decision_note)
                    <div class="alert alert-secondary mt-3 mb-0"><strong>تصمیم مدیر ({{ $idea->decider?->name }} — {{ jdate($idea->decision_at, true) }}):</strong> {{ $idea->decision_note }}</div>
                @endif
                @if ($idea->status === 'approved')
                    <div class="alert alert-info mt-3 mb-0 small"><i class="bi bi-info-circle"></i> تصویب ایده به‌تنهایی مجوز خرج نیست؛ اجرا فقط با ثبت و تأیید عملیات سرمایه‌گذاری مستقل انجام می‌شود.</div>
                @endif
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                @can('update', $idea)<a href="{{ route('ideas.edit', $idea) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> ویرایش/ارسال مجدد</a>@endcan
                @if ($idea->proposed_by === $user->id && ! in_array($idea->status, ['approved', 'executed', 'rejected', 'cancelled']))
                    <form method="POST" action="{{ route('ideas.withdraw', $idea) }}" data-confirm="ایده لغو شود؟">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">لغو ایده</button></form>
                @endif
                @if ($user->isAdmin() && $idea->status === 'approved')
                    <a href="{{ route('investments.create', ['type' => 'buy', 'idea_id' => $idea->id]) }}" class="btn btn-sm btn-primary">ثبت عملیات سرمایه‌گذاری برای اجرا</a>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span>نظرات اعضا</span>
                <span class="small"><span class="text-success">موافق {{ $counts['agree'] ?? 0 }}</span> · <span class="text-danger">مخالف {{ $counts['disagree'] ?? 0 }}</span> · <span class="text-secondary">ممتنع {{ $counts['abstain'] ?? 0 }}</span></span>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($idea->reviews as $r)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between"><strong>{{ $r->member?->name }}</strong><span>{!! status_badge($r->opinion) !!} <span class="small text-muted">{{ jdate($r->updated_at, true) }}</span></span></div>
                        @if ($r->comment)<div class="small mt-1" style="white-space: pre-line">{{ $r->comment }}</div>@endif
                    </li>
                @empty
                    <li class="list-group-item text-muted small">هنوز نظری ثبت نشده است.</li>
                @endforelse
            </ul>
            @if ($reviewable)
            <div class="card-body border-top">
                <form method="POST" action="{{ route('ideas.review', $idea) }}">
                    @csrf
                    <div class="mb-2 d-flex gap-3">
                        @foreach (\App\Models\Idea::OPINIONS as $k => $v)
                            <div class="form-check"><input class="form-check-input" type="radio" name="opinion" id="op_{{ $k }}" value="{{ $k }}" @checked(old('opinion', $myReview?->opinion) === $k) required><label class="form-check-label" for="op_{{ $k }}">{{ $v }}</label></div>
                        @endforeach
                    </div>
                    <textarea name="comment" class="form-control mb-2" rows="2" placeholder="توضیح (اختیاری)">{{ old('comment', $myReview?->comment) }}</textarea>
                    <button class="btn btn-primary btn-sm" type="submit">{{ $myReview ? 'به‌روزرسانی نظر من' : 'ثبت نظر' }}</button>
                    <span class="small text-muted">هر عضو یک نظر جاری دارد؛ تغییرات در تاریخچه حفظ می‌شود.</span>
                </form>
            </div>
            @endif
        </div>

        @if ($idea->investments->count())
            <div class="card mb-3"><div class="card-header">عملیات مرتبط</div><ul class="list-group list-group-flush">
                @foreach ($idea->investments as $t)
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('investments.show', $t) }}">#{{ $t->id }} {{ \App\Models\InvestmentTransaction::TYPES[$t->type] }} {{ $t->asset?->name }}</a><span><span class="money small">{{ money($t->net_amount_irr) }}</span> {!! status_badge($t->status) !!}</span></li>
                @endforeach
            </ul></div>
        @endif
    </div>

    <div class="col-lg-4">
        @if ($user->isAdmin() && $idea->status !== 'draft')
        <div class="card mb-3"><div class="card-header">تصمیم مدیر</div><div class="card-body">
            <form method="POST" action="{{ route('ideas.decide', $idea) }}" data-confirm="وضعیت ایده تغییر کند؟">
                @csrf
                <select name="status" class="form-select mb-2">
                    @foreach (\App\Models\Idea::STATUSES as $k => $v)
                        @continue(in_array($k, ['draft', 'executed']))
                        <option value="{{ $k }}" @selected($idea->status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <textarea name="note" class="form-control mb-2" rows="3" placeholder="دلیل تصمیم (برای تصویب/رد/تعلیق/لغو الزامی)"></textarea>
                <button class="btn btn-primary w-100" type="submit">ثبت تصمیم</button>
            </form>
        </div></div>
        @endif
        <div class="card mb-3"><div class="card-header">تاریخچه وضعیت</div><div class="card-body"><div class="timeline">
            @foreach ($idea->statusHistories->sortBy('id') as $h)
                <div class="timeline-item small">
                    <div>{!! status_badge($h->to_status) !!} <span class="text-muted">{{ jdate($h->created_at, true) }}</span></div>
                    <div class="text-muted">{{ $h->changer?->name }}{{ $h->note ? ': '.$h->note : '' }}</div>
                </div>
            @endforeach
        </div></div></div>
        <div class="card"><div class="card-header">تاریخچه تغییر نظرها</div>
            <ul class="list-group list-group-flush small">
                @forelse ($idea->reviewHistories->sortByDesc('id') as $h)
                    <li class="list-group-item">{{ $h->member?->name }}: {!! status_badge($h->opinion) !!} <span class="text-muted">{{ jdate($h->created_at, true) }}</span></li>
                @empty <li class="list-group-item text-muted">—</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
