<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->visibleInContext()
            ->with(['user:id,name'])
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', 'like', $e.'%'))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('shared.audit-logs', [
            'logs' => $logs,
            'branches' => new Collection,
            'filters' => $filters,
            'layout' => 'layouts.branch',
            'route' => 'branch.audit-logs.index',
        ]);
    }
}
