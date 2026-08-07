<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    /**
     * GET /admin/audit-logs — read-only full audit trail
     */
    public function index()
    {
        $logs = AuditLog::with('user')
            ->latest()
            ->paginate(50);

        return view('admin.audit-logs.index', compact('logs'));
    }
}
