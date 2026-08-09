<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SecurityEventActionRequest;
use App\Models\SecurityEvent;
use App\Services\SecurityEventService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityCommandCenterController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'severity' => ['nullable', 'in:info,low,medium,high,critical'],
            'status' => ['nullable', 'in:new,investigating,contained,resolved,false_positive'],
            'category' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = SecurityEvent::query()->with('assignee')->latest('last_seen_at');
        foreach (['severity', 'status', 'category'] as $filter) {
            $query->when($filters[$filter] ?? null, fn ($builder, $value) => $builder->where($filter, $value));
        }
        $query->when($filters['search'] ?? null, function ($builder, $search) {
            $builder->where(fn ($nested) => $nested
                ->where('event_code', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('source_ip', 'like', "%{$search}%"));
        });

        $summary = [
            'open' => SecurityEvent::open()->count(),
            'critical' => SecurityEvent::open()->whereIn('severity', ['high', 'critical'])->count(),
            'contained_today' => SecurityEvent::whereDate('contained_at', today())->count(),
            'needs_order' => SecurityEvent::where('status', 'new')->count(),
            'operational' => SecurityEvent::open()->where('category', 'operations')->count(),
            'failed_logins_today' => SecurityEvent::where('event_code', 'AUTH.LOGIN_FAILED')->whereDate('last_seen_at', today())->sum('occurrences'),
        ];

        return view('admin.security.index', [
            'events' => $query->paginate(20)->withQueryString(),
            'summary' => $summary,
            'categories' => SecurityEvent::query()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function show(SecurityEvent $securityEvent): View
    {
        return view('admin.security.show', ['event' => $securityEvent->load(['activities.user', 'assignee', 'acknowledger'])]);
    }

    public function action(SecurityEventActionRequest $request, SecurityEvent $securityEvent, SecurityEventService $security): mixed
    {
        $validated = $request->validated();
        $security->transition($securityEvent, $request->user('web'), $validated['action'], $validated['note'] ?? null);

        return back()->with('success', 'تم تنفيذ الأمر وتوثيقه في سجل الحادث.');
    }
}
