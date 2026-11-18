<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        return view('auth.password', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['current_password' => 'رمز فعلی', 'password' => 'رمز جدید']);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'رمز فعلی نادرست است.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'رمز جدید باید با رمز فعلی متفاوت باشد.']);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $request->session()->regenerate();
        AuditLogger::log('auth.password_changed', $user);

        return redirect()->route('dashboard')->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }
}
