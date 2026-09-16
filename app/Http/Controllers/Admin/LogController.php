<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'audit');

        $audit = AuditLog::when($request->filled('action'), function ($query) use ($request) {
            $query->where('action', $request->query('action'));
        })->orderByDesc('id')->paginate(20, pageName: 'audit_page');

        $keamanan = SecurityLog::when($request->filled('type'), function ($query) use ($request) {
            $query->where('type', $request->query('type'));
        })->orderByDesc('id')->paginate(20, pageName: 'security_page');

        return view('admin.logs.index', [
            'tab' => $tab,
            'audit' => $audit,
            'keamanan' => $keamanan,
        ]);
    }
}
