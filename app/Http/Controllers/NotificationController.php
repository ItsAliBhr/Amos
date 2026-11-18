<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AssetValuation;
use App\Models\Contribution;
use App\Models\Idea;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => AppNotification::where('user_id', $request->user()->id)->latest()->paginate(30),
        ]);
    }

    public function open(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        $route = match ($notification->related_type) {
            (new Contribution())->getMorphClass() => route('contributions.show', $notification->related_id),
            (new WithdrawalRequest())->getMorphClass() => route('withdrawals.show', $notification->related_id),
            (new Idea())->getMorphClass() => route('ideas.show', $notification->related_id),
            (new AssetValuation())->getMorphClass() => $request->user()->isAdmin() ? route('valuations.index') : route('dashboard'),
            default => route('notifications.index'),
        };

        return redirect($route);
    }

    public function readAll(Request $request)
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'همه اعلان‌ها خوانده شد.');
    }
}
