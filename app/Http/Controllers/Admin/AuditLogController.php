<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::latest('timestamp')->paginate(50);

        return view('admin.audit-logs.index', compact('logs'));
    }

    public function export()
    {
        $logs = AuditLog::latest('timestamp')->get();

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date/Time', 'User', 'Role', 'Action']);
            foreach ($logs as $log) {
                fputcsv($out, [$log->timestamp->format('Y-m-d H:i:s'), $log->user_name, $log->role, $log->action]);
            }
            fclose($out);
        }, 'audit-logs.csv');
    }
}
