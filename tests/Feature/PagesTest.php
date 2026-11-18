<?php

namespace Tests\Feature;

use App\Domain\Contributions\ContributionService;
use App\Domain\Ideas\IdeaService;
use App\Domain\Investments\InvestmentService;
use App\Domain\Withdrawals\WithdrawalService;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Contribution;
use App\Models\Idea;
use Tests\TestCase;

/** Renders every main page for both roles to catch view/runtime errors. */
class PagesTest extends TestCase
{
    public function test_all_pages_render_for_admin_and_member(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $svc = app(ContributionService::class);
        $c = $svc->approve($svc->create(['type_code' => 'extra', 'amount_irr' => $this->t(1_000_000), 'contribution_date' => now(), 'destination_account_id' => $bank->id], $ali), $this->admin);
        $gold = Asset::create(['name' => 'Gold', 'symbol' => 'G', 'asset_category_id' => AssetCategory::first()->id, 'unit_name' => 'g', 'decimal_places' => 2]);
        $t = app(InvestmentService::class)->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '1', 'unit_price_irr' => $this->t(100_000), 'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
        $idea = app(IdeaService::class)->create(['title' => 'Buy more gold', 'description' => 'desc'], $ali);
        $w = app(WithdrawalService::class)->request($ali, $this->t(1000), 'x');

        $common = ['/dashboard', '/contributions', '/contributions/create', "/contributions/{$c->id}", '/contributions/export', '/investments', "/investments/{$t->id}",
            '/assets', "/assets/{$gold->id}", '/ideas', '/ideas/create', "/ideas/{$idea->id}", '/withdrawals', '/withdrawals/create', "/withdrawals/{$w->id}",
            '/reports', '/reports/member', '/reports/commitments', '/profile', '/password', '/notifications'];
        $adminOnly = ['/admin/approvals', '/admin/users', '/admin/users/create', "/admin/users/{$ali->id}/edit", '/admin/settings', '/admin/categories',
            '/admin/assets/create', "/admin/assets/{$gold->id}/edit", '/admin/cash-accounts', '/admin/cash-accounts/create', "/admin/cash-accounts/{$bank->id}/edit",
            '/admin/ledger', '/admin/ledger/'.\App\Models\LedgerTransaction::value('id'), '/admin/audit', '/admin/audit/'.\App\Models\AuditLog::value('id'), '/admin/docs', '/admin/docs/install', '/valuations', '/valuations/create',
            '/investments/create', '/investments/create?type=sell', '/withdrawals-pool/create', '/reports/fund', '/reports/members', '/reports/pool', '/reports/assets',
            "/reports/member/{$ali->id}", '/reports/commitments?export=csv'];

        foreach (array_merge($common, $adminOnly) as $url) {
            $this->assertSame(200, $this->actingAs($this->admin)->get($url)->getStatusCode(), "admin GET {$url}");
        }
        foreach ($common as $url) {
            $this->assertSame(200, $this->actingAs($ali)->get($url)->getStatusCode(), "member GET {$url}");
        }
    }

    public function test_contribution_form_flow_through_http_with_toman_input(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $this->actingAs($ali)->post('/contributions', [
            'type_code' => 'monthly', 'amount' => '۲,۵۰۰,۰۰۰', 'contribution_date' => '۱۴۰۴/۰۳/۱۵', 'commitment_year' => 1404, 'commitment_month' => 3,
            'destination_account_id' => $bank->id, 'idempotency_key' => 'k1',
        ])->assertRedirect();
        // resubmitting the same form does not create a duplicate
        $this->actingAs($ali)->post('/contributions', [
            'type_code' => 'monthly', 'amount' => '2,500,000', 'contribution_date' => '1404/03/15', 'destination_account_id' => $bank->id, 'idempotency_key' => 'k1',
        ])->assertRedirect();
        $this->assertSame(1, Contribution::count());
        $c = Contribution::first();
        $this->assertSame('25000000.00', (string) $c->amount_irr); // toman x10 = rial
        $this->assertSame('2025-06-05', $c->contribution_date->format('Y-m-d'));

        $this->actingAs($this->admin)->post("/contributions/{$c->id}/approve", ['destination_account_id' => $bank->id])->assertSessionHas('success');
        $this->assertSame('approved', $c->fresh()->status);
    }

    public function test_idea_review_keeps_one_current_opinion_with_history(): void
    {
        $ali = $this->member('ali');
        $reza = $this->member('reza');
        $idea = app(IdeaService::class)->create(['title' => 'T', 'description' => 'D'], $ali);
        $this->actingAs($reza)->post("/ideas/{$idea->id}/review", ['opinion' => 'agree', 'comment' => 'خوب'])->assertSessionHas('success');
        $this->actingAs($reza)->post("/ideas/{$idea->id}/review", ['opinion' => 'disagree'])->assertSessionHas('success');
        $this->assertSame(1, $idea->reviews()->count());
        $this->assertSame('disagree', $idea->reviews()->first()->opinion);
        $this->assertSame(2, $idea->reviewHistories()->count());

        $this->actingAs($reza)->post("/ideas/{$idea->id}/decide", ['status' => 'approved', 'note' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->post("/ideas/{$idea->id}/decide", ['status' => 'approved'])->assertSessionHas('error'); // reason required
        $this->actingAs($this->admin)->post("/ideas/{$idea->id}/decide", ['status' => 'approved', 'note' => 'ok'])->assertSessionHas('success');
        $this->assertSame('approved', $idea->fresh()->status);
        $this->assertSame($this->admin->id, Idea::find($idea->id)->decision_by);
    }
}
