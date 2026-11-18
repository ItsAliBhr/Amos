<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Read-only: there are intentionally no edit/delete routes for audit records. */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.audit.index', [
            'logs' => AuditLog::with('user')
                ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action').'%'))
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
                ->when($request->filled('entity'), fn ($q) => $q->where('entity_type', $request->string('entity')))
                ->orderByDesc('id')->paginate(40)->withQueryString(),
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        return view('admin.audit.show', ['log' => $auditLog->load('user')]);
    }
}
