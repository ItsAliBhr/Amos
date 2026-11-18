<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('profile.show', ['user' => $request->user()]);
    }

    /** Members may change only their contact details; identity/role/commitment are admin-managed. */
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'regex:/^[0-9+\-\s]{7,20}$/'],
        ], [], ['email' => 'ایمیل', 'mobile' => 'موبایل']);
        $user->fill($data)->save();
        [$old, $new] = AuditLogger::changes($user);
        if ($new) {
            AuditLogger::log('user.profile_updated', $user, $old, $new);
        }

        return back()->with('success', 'اطلاعات پروفایل به‌روزرسانی شد.');
    }
}
