<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,first_name,last_name,email,login')
            ->latest('created_at');

        // Filter by action type
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in metadata
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereJsonContains('metadata', $search)
                    ->orWhereJsonContains('old_values', $search)
                    ->orWhereJsonContains('new_values', $search);
            });
        }

        $logs = $query->paginate(50);

        // Get unique actions for filter dropdown
        $actions = AuditLog::distinct()->pluck('action')->sort()->values();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $this->sanitizeFilters($request->only(['action', 'user_id', 'date_from', 'date_to', 'search'])),
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load('user:id,name,first_name,last_name,email,login');

        return Inertia::render('Admin/AuditLogs/Show', [
            'log' => $auditLog,
        ]);
    }
}
