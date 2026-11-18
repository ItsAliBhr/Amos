<?php

namespace App\Domain\Valuations;

use App\Domain\Accounting\NavService;
use App\Domain\DomainException;
use App\Domain\Investments\InvestmentService;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\Money;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/** Valuations are append-only: each one is a new record; history is never overwritten. */
class ValuationService
{
    public function __construct(private NavService $nav, private InvestmentService $investments)
    {
    }

    public function create(array $data, User $admin): AssetValuation
    {
        if (! $admin->isAdmin()) {
            throw new DomainException('فقط مدیر مجاز به ارزش‌گذاری است.');
        }
        if (! empty($data['idempotency_key']) && ($existing = AssetValuation::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing;
        }
        $asset = Asset::find($data['asset_id'] ?? 0);
        if (! $asset) {
            throw new DomainException('ارزش‌گذاری فقط برای دارایی معتبر امکان‌پذیر است.');
        }
        $price = Money::of($data['unit_price_irr'])->toScale(8, RoundingMode::HalfUp);
        if ($price->isNegative() || $price->isZero()) {
            throw new DomainException('قیمت واحد باید مثبت باشد.');
        }
        $qty = $this->investments->holding($asset->id)['quantity'];

        $v = DB::transaction(function () use ($asset, $price, $qty, $data, $admin) {
            $v = AssetValuation::create([
                'asset_id' => $asset->id,
                'valuation_date' => $data['valuation_date'],
                'unit_price_irr' => (string) $price,
                'total_quantity' => (string) $qty->toScale(8),
                'total_value_irr' => (string) Money::irr($qty->multipliedBy($price)),
                'source' => $data['source'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'pending',
                'created_by' => $admin->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);
            AuditLogger::log('valuation.created', $v, null, $v->only(['asset_id', 'valuation_date', 'unit_price_irr', 'total_value_irr']));

            return $v;
        });

        return ! empty($data['approve_now']) ? $this->approve($v, $admin) : $v;
    }

    public function approve(AssetValuation $valuation, User $admin): AssetValuation
    {
        if (! $admin->isAdmin()) {
            throw new DomainException('فقط مدیر مجاز است.');
        }

        return DB::transaction(function () use ($valuation, $admin) {
            $this->nav->lockFund();
            $v = AssetValuation::whereKey($valuation->id)->lockForUpdate()->firstOrFail();
            if ($v->status !== 'pending') {
                throw new DomainException('این ارزش‌گذاری در وضعیت قابل تأیید نیست.');
            }
            $v->forceFill(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()])->save();
            $this->nav->recordSnapshot();
            AuditLogger::log('valuation.approved', $v, ['status' => 'pending'], ['status' => 'approved']);
            Notifier::allActive('valuation.approved', 'ارزش‌گذاری جدید ثبت شد', $v->asset->name.' - قیمت واحد '.Money::format($v->unit_price_irr), $v, $admin->id);

            return $v;
        });
    }

    public function reject(AssetValuation $valuation, User $admin, string $reason): AssetValuation
    {
        if (trim($reason) === '') {
            throw new DomainException('ثبت دلیل رد الزامی است.');
        }

        return DB::transaction(function () use ($valuation, $admin, $reason) {
            $v = AssetValuation::whereKey($valuation->id)->lockForUpdate()->firstOrFail();
            if ($v->status !== 'pending') {
                throw new DomainException('این ارزش‌گذاری در وضعیت قابل رد نیست.');
            }
            $v->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $admin->id, 'approved_at' => now()])->save();
            AuditLogger::log('valuation.rejected', $v, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason]);

            return $v;
        });
    }
}
