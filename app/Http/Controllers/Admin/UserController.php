<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Members\CommitmentService;
use App\Domain\Members\MemberService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Amount;
use App\Rules\JalaliDate;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private MemberService $members, private CommitmentService $commitments)
    {
    }

    public function index(Request $request)
    {
        $users = User::withTrashed()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q').'%')->orWhere('username', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['status' => 'active', 'role' => 'member'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        [$user, $password] = $this->members->create($data, $request->user());

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'کاربر ایجاد شد.')
            ->with('temp_password', $password);
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user,
            'commitments' => $this->commitments->history($user, 12),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        unset($data['password']);
        $this->members->update($user, $data, $request->user());

        return back()->with('success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $password = $this->members->resetPassword($user, $request->user());

        return back()->with('success', 'رمز عبور بازنشانی شد. کاربر باید در اولین ورود رمز را تغییر دهد.')->with('temp_password', $password);
    }

    public function destroy(Request $request, User $user)
    {
        $this->members->delete($user, $request->user());

        return redirect()->route('admin.users.index')->with('success', 'کاربر حذف شد.');
    }

    public function commitment(Request $request, User $user)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:1390,1500'],
            'month' => ['required', 'integer', 'between:1,12'],
            'commitment' => ['required', new Amount(true)],
            'exemption' => ['nullable', new Amount(true)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->commitments->setOverride($user, $data['year'], $data['month'],
            (string) Money::fromInput($data['commitment']),
            (string) Money::fromInput($data['exemption'] ?? '0'),
            $data['note'] ?? null, $request->user());

        return back()->with('success', 'تعهد ماه '.Jalali::monthLabel($data['year'], $data['month']).' به‌روزرسانی شد.');
    }

    private function validated(Request $request, ?User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.\-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
            'monthly_commitment' => ['nullable', new Amount(true)],
            'joined_at' => ['nullable', new JalaliDate()],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'password' => [$user ? 'prohibited' : 'nullable', Password::min(8)->letters()->numbers()],
        ], [], ['name' => 'نام', 'username' => 'نام کاربری', 'mobile' => 'موبایل', 'role' => 'نقش', 'status' => 'وضعیت', 'password' => 'رمز اولیه']);
        $data['username'] = mb_strtolower($data['username']);
        $data['monthly_commitment_irr'] = isset($data['monthly_commitment']) && $data['monthly_commitment'] !== '' ? (string) Money::fromInput($data['monthly_commitment']) : null;
        $data['joined_at'] = ! empty($data['joined_at']) ? Jalali::parse($data['joined_at'])->format('Y-m-d') : ($user?->joined_at?->format('Y-m-d') ?? now()->toDateString());
        unset($data['monthly_commitment']);

        return $data;
    }
}
