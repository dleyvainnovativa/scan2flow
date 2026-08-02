<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $actions = ['viewed', 'downloaded', 'edited', 'deleted', 'uploaded', 'login'];

        return view('audit.index', compact('logs', 'actions'));
    }
}
