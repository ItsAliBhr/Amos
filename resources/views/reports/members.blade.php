@extends('layouts.app')
@section('title', 'گزارش اعضا')
@section('content')
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>عضو</th><th>وضعیت</th><th>واحد</th><th>ارزش روز</th><th>سرمایه خالص</th><th>سود/زیان</th><th>تعهد ماه جاری</th><th></th></tr></thead>
    <tbody>
    @foreach ($rows as $r)
        <tr>
            <td>{{ $r['user']->name }} <span class="small text-muted ltr">{{ $r['user']->username }}</span></td>
            <td>{!! status_badge($r['user']->trashed() ? 'inactive' : $r['user']->status) !!}</td>
            <td class="num">{{ num($r['units'], 4) }}</td>
            <td class="money">{{ money($r['value']) }}</td>
            <td class="money">{{ money($r['net']) }}</td>
            <td class="money {{ $r['pnl']->isNegative() ? 'negative' : 'positive' }}">{{ money($r['pnl']) }}</td>
            <td>{!! status_badge($r['commitment']['status']) !!}</td>
            <td><a href="{{ route('reports.member', $r['user']) }}" class="btn btn-sm btn-light">جزئیات</a></td>
        </tr>
    @endforeach
    </tbody></table></div></div>
@endsection
