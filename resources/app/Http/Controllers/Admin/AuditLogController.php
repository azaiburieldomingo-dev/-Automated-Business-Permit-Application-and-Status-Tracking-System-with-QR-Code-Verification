<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('actor')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->query('action').'%'))
            ->when($request->filled('actor'), fn ($q) => $q->where('actor_id', $request->query('actor')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->query('date')))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => $actions,
        ]);
    }
}
