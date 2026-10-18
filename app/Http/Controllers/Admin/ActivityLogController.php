<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Log;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor_type' => ['nullable', 'in:admin,employer,jobseeker'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $logs = Log::query()
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['actor_type'] ?? null, fn ($query, $type) => $query->where('actor_type', $type))
            ->when($filters['actor_id'] ?? null, fn ($query, $id) => $query->where('actor_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('cdate', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('cdate', '<=', $to))
            ->orderByDesc('cdate')->orderByDesc('Id')->paginate(30)->withQueryString();
        return view('admin.logs.index', compact('logs'));
    }
}
