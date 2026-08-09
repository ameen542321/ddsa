@extends('dashboard.app')

@section('content')
@section('title', 'مركز القيادة الأمنية')
@php
    $severityLabels = ['info' => 'معلومة', 'low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع', 'critical' => 'حرج'];
    $severityBadges = ['info' => 'ui-badge-info', 'low' => 'ui-badge-info', 'medium' => 'ui-badge-warning', 'high' => 'ui-badge-danger', 'critical' => 'ui-badge-danger'];
    $statusLabels = ['new' => 'جديد', 'investigating' => 'قيد التحقيق', 'contained' => 'تم الاحتواء', 'resolved' => 'تم الحل', 'false_positive' => 'إنذار كاذب'];
@endphp

<div class="security-command-header">
    <div>
        <h1 class="ui-title text-2xl font-bold">مركز القيادة الأمنية</h1>
        <p class="ui-text-soft mt-1">سيدي، جميع المواقع تحت المراقبة. تعرض هذه الصفحة الرصد والاحتواء والنتائج المتحقق منها.</p>
    </div>
    <span role="status" aria-live="polite" class="ui-badge {{ $summary['critical'] > 0 ? 'ui-badge-danger' : ($summary['open'] > 0 ? 'ui-badge-warning' : 'ui-badge-success') }}">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        {{ $summary['critical'] > 0 ? 'حالة استنفار' : ($summary['open'] > 0 ? 'تحت المراقبة' : 'لا تهديدات نشطة') }}
    </span>
</div>

<div class="security-summary-grid" aria-label="ملخص الموقف الأمني">
    @foreach([
        ['البلاغات المفتوحة', $summary['open'], 'fa-folder-open'],
        ['مرتفعة وحرجة', $summary['critical'], 'fa-triangle-exclamation'],
        ['تم احتواؤها اليوم', $summary['contained_today'], 'fa-shield'],
        ['تحتاج أمرك', $summary['needs_order'], 'fa-bell'],
        ['مشكلات التشغيل', $summary['operational'], 'fa-server'],
        ['محاولات دخول اليوم', $summary['failed_logins_today'], 'fa-key'],
    ] as [$label, $value, $icon])
        <article class="ui-card security-summary-card">
            <div><p class="ui-text-soft">{{ $label }}</p><p class="ui-title text-2xl font-bold mt-1">{{ number_format($value) }}</p></div>
            <div class="ui-stat-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></div>
        </article>
    @endforeach
</div>

<form method="GET" class="ui-card security-filter-panel" aria-label="تصفية البلاغات الأمنية">
    <label><span class="ui-field-label">البحث</span><input class="ui-input w-full mt-1" type="search" name="search" value="{{ request('search') }}" placeholder="رمز البلاغ أو المصدر" autocomplete="off"></label>
    <label><span class="ui-field-label">الخطورة</span><select class="ui-input w-full mt-1" name="severity">
        <option value="">كل مستويات الخطورة</option>
        @foreach($severityLabels as $value => $label)<option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>@endforeach
    </select></label>
    <label><span class="ui-field-label">الحالة</span><select class="ui-input w-full mt-1" name="status">
        <option value="">كل الحالات</option>
        @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
    </select></label>
    <label><span class="ui-field-label">النوع</span><select class="ui-input w-full mt-1" name="category">
        <option value="">كل الأنواع</option>
        @foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach
    </select></label>
    <div class="security-filter-actions"><button class="ui-btn ui-btn-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i>تطبيق</button><a class="ui-btn ui-btn-secondary" href="{{ route('admin.security.index') }}">مسح</a></div>
</form>

<div class="ui-table-wrap">
    <div class="overflow-x-auto" tabindex="0" aria-label="جدول البلاغات الأمنية؛ يمكن تمريره أفقيًا عند الحاجة">
        <table class="ui-table w-full">
            <caption class="sr-only">البلاغات الأمنية المطابقة للفلاتر الحالية</caption>
            <thead><tr><th>البلاغ</th><th>الخطورة</th><th>الحالة</th><th>التكرار</th><th>آخر رصد</th><th>الأمر</th></tr></thead>
            <tbody>
            @forelse($events as $event)
                <tr>
                    <td data-label="البلاغ"><strong class="ui-title">{{ $event->title }}</strong><span class="block ui-text-muted text-sm mt-1">{{ $event->event_code }} · {{ $event->category }}@if($event->masked_source_ip) · {{ $event->masked_source_ip }}@endif</span></td>
                    <td data-label="الخطورة"><span class="ui-badge {{ $severityBadges[$event->severity] ?? 'ui-badge-info' }}">{{ $severityLabels[$event->severity] ?? $event->severity }}</span></td>
                    <td data-label="الحالة"><span class="ui-text-soft">{{ $statusLabels[$event->status] ?? $event->status }}</span></td>
                    <td data-label="التكرار">{{ number_format($event->occurrences) }}</td>
                    <td data-label="آخر رصد"><time datetime="{{ $event->last_seen_at->toIso8601String() }}">{{ $event->last_seen_at->diffForHumans() }}</time></td>
                    <td data-label="الأمر"><a class="ui-btn ui-btn-secondary" href="{{ route('admin.security.show', $event) }}"><span>التقرير والأوامر</span><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a></td>
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
