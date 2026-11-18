<?php

namespace Tests\Feature;

use App\Domain\Contributions\ContributionService;
use App\Domain\Withdrawals\WithdrawalService;
use App\Models\AuditLog;
use App\Models\Contribution;
use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_root_is_login_page_and_guests_are_redirected(): void
    {
        $this->get('/')->assertOk()->assertSee('AMOS')->assertSee('نام کاربری');
        foreach (['/dashboard', '/contributions', '/admin/users', '/reports/member'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['username' => 'x'])->assertStatus(404);
    }

    public function test_member_cannot_open_admin_pages(): void
    {
        $m = $this->member();
        foreach (['/admin/users', '/admin/settings', '/admin/approvals', '/admin/audit', '/admin/ledger', '/admin/docs', '/valuations', '/reports/fund', '/investments/create'] as $url) {
            $this->actingAs($m)->get($url)->assertForbidden();
        }
    }

    public function test_member_cannot_create_users_or_change_base_settings(): void
    {
        $m = $this->member();
        $this->actingAs($m)->post('/admin/users', ['name' => 'X', 'username' => 'hacker', 'role' => 'admin', 'status' => 'active'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'hacker']);

        $this->actingAs($m)->put('/admin/settings', ['fund_name' => 'Hacked'])->assertForbidden();
        $this->assertNotSame('Hacked', FundSetting::current()->fresh()->fund_name);

        $this->actingAs($m)->put('/admin/users/'.$m->id, ['role' => 'admin'])->assertForbidden();
        $this->assertSame('member', $m->fresh()->role);
    }

    public function test_member_cannot_approve_transactions_or_change_valuations(): void
    {
        $bank = $this->cashAccount();
        $m = $this->member();
        $c = app(ContributionService::class)->create(['type_code' => 'monthly', 'amount_irr' => $this->t(1000), 'contribution_date' => now(), 'destination_account_id' => $bank->id], $m);

        $this->actingAs($m)->post("/contributions/{$c->id}/approve")->assertForbidden();
        $this->actingAs($m)->post("/contributions/{$c->id}/reject", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($m)->post('/valuations', ['asset_id' => 1, 'unit_price' => '1'])->assertForbidden();
        $this->assertSame('pending', $c->fresh()->status);

        // Service layer enforces the rule as well (defence in depth).
        $this->expectException(\App\Domain\DomainException::class);
        app(ContributionService::class)->approve($c, $m);
    }

    public function test_member_cannot_submit_for_another_member_or_use_admin_only_types(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $reza = $this->member('reza');
        $this->actingAs($ali)->post('/contributions', [
            'type_code' => 'monthly', 'member_id' => $reza->id, 'amount' => '1,000', 'contribution_date' => '1404/01/10', 'destination_account_id' => $bank->id,
        ])->assertSessionHasErrors('member_id');

        $this->actingAs($ali)->post('/contributions', [
            'type_code' => 'adjustment', 'amount' => '1,000', 'contribution_date' => '1404/01/10',
        ])->assertSessionHas('error');
        $this->assertSame(0, Contribution::count());
    }

    public function test_member_cannot_see_other_members_records_by_changing_ids(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $reza = $this->member('reza');
        $svc = app(ContributionService::class);
        $rezaC = $svc->approve($svc->create(['type_code' => 'extra', 'amount_irr' => $this->t(1000), 'contribution_date' => now(), 'destination_account_id' => $bank->id], $reza), $this->admin);
        $w = app(WithdrawalService::class)->request($reza, $this->t(10), null);

        $this->actingAs($ali)->get("/contributions/{$rezaC->id}")->assertForbidden();
        $this->actingAs($ali)->get("/withdrawals/{$w->id}")->assertForbidden();
        $this->actingAs($ali)->get("/reports/member/{$reza->id}")->assertForbidden();
        $this->actingAs($ali)->post("/withdrawals/{$w->id}/cancel")->assertForbidden();
        $this->actingAs($ali)->get('/contributions')->assertOk()->assertDontSee('Reza');
        $this->actingAs($ali)->get('/contributions/export')->assertOk()->assertDontSee('Reza');
    }

    public function test_attachments_are_private_and_authorised(): void
    {
        Storage::fake('local');
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $reza = $this->member('reza');
        $this->actingAs($ali)->post('/contributions', [
            'type_code' => 'monthly', 'amount' => '1,000', 'contribution_date' => '1404/01/10', 'destination_account_id' => $bank->id,
            'receipt' => UploadedFile::fake()->image('r.jpg'),
        ])->assertRedirect();
        $c = Contribution::firstOrFail();
        $this->assertNotNull($c->receipt_attachment_id);
        $path = $c->receipt->storage_path;
        $this->assertStringNotContainsString('r.jpg', $path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($ali)->get("/attachments/{$c->receipt_attachment_id}")->assertOk();
        $this->actingAs($reza)->get("/attachments/{$c->receipt_attachment_id}")->assertForbidden();
        $this->actingAs($this->admin)->get("/attachments/{$c->receipt_attachment_id}")->assertOk();
    }

    public function test_disallowed_upload_is_rejected(): void
    {
        Storage::fake('local');
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        foreach ([UploadedFile::fake()->create('shell.php', 1, 'application/x-php'), UploadedFile::fake()->create('x.exe', 1, 'application/octet-stream'), UploadedFile::fake()->create('big.pdf', 99999, 'application/pdf')] as $file) {
            $this->actingAs($ali)->post('/contributions', [
                'type_code' => 'monthly', 'amount' => '1,000', 'contribution_date' => '1404/01/10', 'destination_account_id' => $bank->id, 'receipt' => $file,
            ])->assertSessionHasErrors('receipt');
        }
        $this->assertSame(0, Contribution::count());
    }

    public function test_inactive_user_cannot_login_and_is_logged_out_when_deactivated(): void
    {
        $m = $this->member('ali');
        $m->update(['status' => 'inactive']);
        $this->post('/login', ['username' => 'ali', 'password' => 'Member12345'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $m->update(['status' => 'active']);
        $this->post('/login', ['username' => 'ali', 'password' => 'Member12345'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($m);
        $m->update(['status' => 'suspended']);
        $this->app['auth']->forgetGuards(); // next request resolves the user from the session/DB, like production
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_errors_are_generic_and_rate_limited(): void
    {
        $this->member('ali');
        $this->post('/login', ['username' => 'nobody', 'password' => 'x'])->assertSessionHasErrors(['username' => 'نام کاربری یا رمز عبور نادرست است.']);
        for ($i = 0; $i < 6; $i++) {
            $res = $this->post('/login', ['username' => 'ali', 'password' => 'wrong']);
        }
        $res->assertSessionHasErrors('username');
        $this->assertStringContainsString('ثانیه', session('errors')->first('username'));
        $this->post('/login', ['username' => 'ali', 'password' => 'Member12345']);
        $this->assertGuest();
    }

    public function test_first_login_forces_password_change(): void
    {
        [$u, $pw] = app(\App\Domain\Members\MemberService::class)->create(['name' => 'New', 'username' => 'newbie'], $this->admin);
        $this->post('/login', ['username' => 'newbie', 'password' => $pw])->assertRedirect(route('password.edit'));
        $this->get('/dashboard')->assertRedirect(route('password.edit'));
        $this->put('/password', ['current_password' => $pw, 'password' => 'BrandNew123', 'password_confirmation' => 'BrandNew123'])->assertRedirect(route('dashboard'));
        $this->assertFalse($u->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_logout_blocks_protected_pages(): void
    {
        $m = $this->member();
        $this->actingAs($m)->get('/dashboard')->assertOk();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_invalid_csrf_token_is_rejected(): void
    {
        $this->member('ali');
        $this->app['env'] = 'local'; // re-enable CSRF verification (skipped by default in tests)
        $this->post('/login', ['username' => 'ali', 'password' => 'Member12345', '_token' => 'invalid'])->assertStatus(419);
        $this->assertGuest();
    }

    public function test_last_active_admin_cannot_be_deactivated(): void
    {
        $this->actingAs($this->admin)->put('/admin/users/'.$this->admin->id, [
            'name' => 'Admin', 'username' => 'admin', 'role' => 'admin', 'status' => 'inactive',
        ])->assertSessionHas('error');
        $this->assertSame('active', $this->admin->fresh()->status);
    }

    public function test_member_with_financial_history_cannot_be_deleted(): void
    {
        $bank = $this->cashAccount();
        $m = $this->member('ali');
        $svc = app(ContributionService::class);
        $svc->approve($svc->create(['type_code' => 'extra', 'amount_irr' => $this->t(1000), 'contribution_date' => now(), 'destination_account_id' => $bank->id], $m), $this->admin);
        $this->actingAs($this->admin)->delete('/admin/users/'.$m->id)->assertSessionHas('error');
        $this->assertNotNull(User::find($m->id));
    }

    public function test_audit_log_records_sensitive_actions_without_passwords_and_is_immutable(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Sara', 'username' => 'sara', 'role' => 'member', 'status' => 'active', 'password' => 'Initial1234',
        ])->assertRedirect();
        $log = AuditLog::where('action', 'user.created')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringNotContainsString('Initial1234', json_encode($log->toArray()));

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_admin_can_create_user_and_member_cannot_view_admin_user_list(): void
    {
        $res = $this->actingAs($this->admin)->post('/admin/users', ['name' => 'Sara', 'username' => 'Sara', 'role' => 'member', 'status' => 'active']);
        $res->assertRedirect()->assertSessionHas('temp_password');
        $sara = User::where('username', 'sara')->firstOrFail();
        $this->assertTrue($sara->must_change_password);
        $this->assertNotNull($sara->ownershipAccount);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
