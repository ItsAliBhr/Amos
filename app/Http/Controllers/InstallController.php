<?php

namespace App\Http\Controllers;

use App\Services\Install\Installer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Web installer for hosts without SSH/Composer. Reachable only before installation (see EnsureInstalled). */
class InstallController extends Controller
{
    public function show(Request $request)
    {
        return view('install.index', [
            'checks' => Installer::requirements(),
            'url' => $request->getSchemeAndHttpHost(),
        ]);
    }

    public function store(Request $request, Installer $installer)
    {
        $data = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'db_username' => ['required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'fund_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'admin_password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ], [], [
            'db_host' => 'میزبان دیتابیس', 'db_database' => 'نام دیتابیس', 'db_username' => 'نام کاربری دیتابیس',
            'admin_username' => 'نام کاربری مدیر', 'admin_password' => 'رمز مدیر', 'app_url' => 'آدرس سامانه',
        ]);

        foreach (Installer::requirements() as [$label, $ok]) {
            if (! $ok) {
                return back()->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']))
                    ->with('error', 'پیش‌نیاز برآورده نشده: '.$label);
            }
        }

        try {
            $installer->install(
            ['host' => $data['db_host'], 'port' => $data['db_port'], 'database' => $data['db_database'], 'username' => $data['db_username'], 'password' => $data['db_password'] ?? ''],
            ['name' => $data['admin_name'], 'username' => $data['admin_username'], 'password' => $data['admin_password']],
            ['url' => $data['app_url'], 'fund_name' => $data['fund_name']],
            );
        } catch (\App\Domain\DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Show the real cause on the installer page instead of a bare HTTP 500.
            \Illuminate\Support\Facades\Log::error('AMOS install failed', ['exception' => $e]);

            return back()->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']))
                ->with('error', 'نصب ناموفق بود: '.Installer::safeMessage($e).' (جزئیات کامل در amos_app/storage/logs)');
        }

        $request->session()->flush();

        return redirect('/')->with('success', 'نصب با موفقیت انجام شد. با حساب مدیر وارد شوید.');
    }
}
