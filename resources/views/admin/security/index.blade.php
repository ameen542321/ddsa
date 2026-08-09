@extends('dashboard.app')

@section('content')
@php
    $severityLabels = ['info' => 'معلومة', 'low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع', 'critical' => 'حرج'];
    $severityBadges = ['info' => 'ui-badge-info', 'low' => 'ui-badge-info', 'medium' => 'ui-badge-warning', 'high' => 'ui-badge-danger', 'critical' => 'ui-badge-danger'];
    $statusLabels = ['new' => 'جديد', 'investigating' => 'قيد التحقيق', 'contained' => 'تم الاحتواء', 'resolved' => 'تم الحل', 'false_positive' => 'إنذار كاذب'];
@endphp

<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <h1 class="ui-title text-2xl font-bold">مركز القيادة الأمنية</h1>
        <p class="ui-text-soft mt-1">سيدي، جميع المواقع تحت المراقبة. تعرض هذه الصفحة الرصد والاحتواء والنتائج المتحقق منها.</p>
    </div>
    <span class="ui-badge {{ $summary['critical'] > 0 ? 'ui-badge-danger' : ($summary['open'] > 0 ? 'ui-badge-warning' : 'ui-badge-success') }}">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        {{ $summary['critical'] > 0 ? 'حالة استنفار' : ($summary['open'] > 0 ? 'تحت المراقبة' : 'لا تهديدات نشطة') }}
    </span>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
    @foreach([
        ['البلاغات المفتوحة', $summary['open'], 'fa-folder-open'],
        ['مرتفعة وحرجة', $summary['critical'], 'fa-triangle-exclamation'],
        ['تم احتواؤها اليوم', $summary['contained_today'], 'fa-shield'],
        ['تحتاج أمرك', $summary['needs_order'], 'fa-bell'],
        ['مشكلات التشغيل', $summary['operational'], 'fa-server'],
        ['محاولات دخول اليوم', $summary['failed_logins_today'], 'fa-key'],
    ] as [$label, $value, $icon])
        <div class="ui-card p-5 flex items-center justify-between gap-4">
            <div><p class="ui-text-soft">{{ $label }}</p><p class="ui-title text-2xl font-bold mt-1">{{ number_format($value) }}</p></div>
            <div class="ui-stat-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></div>
        </div>
    @endforeach
</div>

<form method="GET" class="ui-card p-4 grid grid-cols-1 md:grid-cols-5 gap-3 mb-6" aria-label="تصفية البلاغات الأمنية">
    <input class="ui-input" type="search" name="search" value="{{ request('search') }}" placeholder="رمز البلاغ أو المصدر">
    <select class="ui-input" name="severity">
        <option value="">كل مستويات الخطورة</option>
        @foreach($severityLabels as $value => $label)<option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>@endforeach
    </select>
    <select class="ui-input" name="status">
        <option value="">كل الحالات</option>
        @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
    </select>
    <select class="ui-input" name="category">
        <option value="">كل الأنواع</option>
        @foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach
    </select>
    <button class="ui-btn ui-btn-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i>تطبيق الفلاتر</button>
</form>

<div class="ui-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="ui-table w-full">
            <thead><tr><th>البلاغ</th><th>الخطورة</th><th>الحالة</th><th>التكرار</th><th>آخر رصد</th><th>الأمر</th></tr></thead>
            <tbody>
            @forelse($events as $event)
                <tr>
                    <td><strong class="ui-title">{{ $event->title }}</strong><span class="block ui-text-muted text-sm mt-1">{{ $event->event_code }} · {{ $event->category }}@if($event->masked_source_ip) · {{ $event->masked_source_ip }}@endif</span></td>
                    <td><span class="ui-badge {{ $severityBadges[$event->severity] ?? 'ui-badge-info' }}">{{ $severityLabels[$event->severity] ?? $event->severity }}</span></td>
                    <td><span class="ui-text-soft">{{ $statusLabels[$event->status] ?? $event->status }}</span></td>
                    <td>{{ number_format($event->occurrences) }}</td>
                    <td><time datetime="{{ $event->last_seen_at->toIso8601String() }}">{{ $event->last_seen_at->diffForHumans() }}</time></td>
                    <td><a class="ui-btn ui-btn-secondary" href="{{ route('admin.security.show', $event) }}">التقرير والأوامر</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-8 ui-text-muted">سيدي، لا توجد بلاغات مطابقة للفلاتر الحالية.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $events->links() }}</div>
@endsection
