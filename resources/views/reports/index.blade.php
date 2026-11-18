@extends('layouts.app')
@section('title', 'گزارش‌ها')
@section('content')
@php $admin = auth()->user()->isAdmin();
$items = $admin ? [
  ['reports.fund', 'گزارش کل صندوق', 'ارزش خالص، نقدینگی، دارایی‌ها، سرمایه اعضا، Pool، سود/زیان و ترکیب دارایی', 'bank'],
  ['reports.members', 'گزارش اعضا', 'واحد، ارزش روز، سرمایه خالص و سود/زیان همه اعضا', 'people'],
  ['reports.pool', 'گزارش Pool', 'کمک‌ها، واحد، ارزش روز، سود/زیان و گردش', 'piggy-bank'],
  ['reports.assets', 'گزارش دارایی‌ها', 'مقدار، بهای تمام‌شده، ارزش روز، سود/زیان و تاریخچه', 'gem'],
  ['reports.commitments', 'تعهد ماهانه و معوقات', 'وضعیت تعهد ماهانه اعضا و پرداخت‌های معوق', 'calendar-check'],
  ['contributions.index', 'گزارش واریزی‌ها', 'فیلتر و خروجی CSV/Excel', 'cash-coin'],
  ['reports.member', 'گزارش من', 'سبد اختصاصی من', 'person'],
] : [
  ['reports.member', 'گزارش سبد من', 'واریزی، واحد، ارزش روز، سود/زیان، تعهد ماهانه و برداشت‌ها', 'person'],
  ['reports.commitments', 'تعهد ماهانه من', 'وضعیت تعهد ماه انتخابی', 'calendar-check'],
  ['contributions.index', 'واریزی‌های من', 'فیلتر و خروجی CSV', 'cash-coin'],
]; @endphp
<div class="row g-3">
@foreach ($items as [$r, $t, $d, $i])
    <div class="col-md-6 col-xl-4"><a href="{{ route($r) }}" class="card card-body h-100 text-decoration-none text-reset">
        <div class="d-flex gap-3 align-items-center"><div class="stat-card"><div class="icon"><i class="bi bi-{{ $i }}"></i></div></div><div><div class="fw-bold">{{ $t }}</div><div class="small text-muted">{{ $d }}</div></div></div>
    </a></div>
@endforeach
</div>
@endsection
