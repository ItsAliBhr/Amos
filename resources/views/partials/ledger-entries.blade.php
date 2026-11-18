<div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead><tr><th>حساب</th><th>جزئیات</th><th>مقدار</th><th>بدهکار</th><th>بستانکار</th></tr></thead>
        <tbody>
        @foreach ($entries as $e)
            <tr>
                <td>{{ \App\Domain\Accounting\AccountCode::label($e->account_code) }} <span class="text-muted small num">{{ $e->account_code }}</span></td>
                <td class="small">
                    {{ $e->cashAccount?->name }}{{ $e->asset?->name }}{{ $e->ownershipAccount?->displayName() }}
                </td>
                <td class="num">{{ (float) $e->quantity != 0 ? num($e->quantity, 8) : '' }}</td>
                <td class="money">{{ (float) $e->debit_irr ? money($e->debit_irr) : '' }}</td>
                <td class="money">{{ (float) $e->credit_irr ? money($e->credit_irr) : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
