<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'ارزش فعلی سبد اختصاصی من', 'value' => money($myValue), 'icon' => 'wallet2'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'تعداد واحدهای من', 'value' => num($myUnits, 4), 'icon' => 'stack'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'مجموع واریزی اختصاصی تأییدشده', 'value' => money($myContributed), 'icon' => 'cash-stack'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'سود/زیان سبد من', 'value' => money($myPnl), 'icon' => 'graph-up', 'class' => $myPnl->isNegative() ? 'negative' : 'positive', 'hint' => 'ارزش فعلی منهای سرمایه خالص'])</div>
</div>
