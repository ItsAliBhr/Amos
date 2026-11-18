<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['username' => 'نام کاربری', 'password' => 'رمز عبور']);

        $key = 'login:'.Str::lower($data['username']).'|'.$request->ip();
        $max = config('amos.login_max_attempts');
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['username' => "تعداد تلاش‌های ناموفق زیاد است. {$seconds} ثانیه دیگر تلاش کنید."]);
        }

        $generic = 'نام کاربری یا رمز عبور نادرست است.';
        $user = User::where('username', Str::lower($data['username']))->first();

        if (! $user || ! Auth::validate(['username' => $user->username, 'password' => $data['password']])) {
            RateLimiter::hit($key, config('amos.login_decay_seconds'));
            AuditLogger::log('auth.login_failed', 'User', null, ['username' => Str::limit($data['username'], 64)]);
            throw ValidationException::withMessages(['username' => $generic]);
        }

        if (! $user->isActive()) {
            RateLimiter::hit($key, config('amos.login_decay_seconds'));
            AuditLogger::log('auth.login_blocked_inactive', $user, null, null, $user->id);
            throw ValidationException::withMessages(['username' => 'حساب کاربری شما فعال نیست. با مدیر صندوق تماس بگیرید.']);
        }

        Auth::login($user, $request->boolean('remember'));
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        AuditLogger::log('auth.login', $user, null, null, $user->id);

        if ($user->must_change_password) {
            return redirect()->route('password.edit');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLogger::log('auth.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
