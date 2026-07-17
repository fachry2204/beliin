<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('audit.view');
        $rows = ActivityLog::query()->with('user:id,name,email')
            ->when($request->search, fn ($query, $search) => $query->where('module', 'like', "%{$search}%")->orWhere('action', 'like', "%{$search}%"))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Settings/Audit', ['rows' => $rows]);
    }
}
