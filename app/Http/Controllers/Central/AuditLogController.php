<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $branches = Branch::query()->visibleTo($request->user())->ordered()->pluck('name', 'id');

        $logs = AuditLog::query()
            ->visibleInContext()
            ->with(['user:id,name', 'branch:id,name'])
            ->when($filters['branch_id'] ?? null, function ($q, $id) use ($branches) {
                abort_unless($branches->has($id), 404);
                $q->where('branch_id', $id);
            })
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', 'like', $e.'%'))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('shared.audit-logs', [
            'logs' => $logs,
            'branches' => $branches,
            'filters' => $filters,
            'layout' => 'layouts.central',
            'route' => 'central.audit-logs.index',
        ]);
    }
}
