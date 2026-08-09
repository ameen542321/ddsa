@extends('dashboard.app')

@section('content')
@php
    $statusLabels = ['new' => 'جديد', 'investigating' => 'قيد التحقيق', 'contained' => 'تم الاحتواء', 'resolved' => 'تم الحل والتحقق', 'false_positive' => 'إنذار كاذب'];
    $actionLabels = ['acknowledge' => 'استلام البلاغ', 'contain' => 'اعتماد الاحتواء', 'block_source' => 'تقييد المصدر 30 دقيقة', 'resolve' => 'إغلاق بعد التحقق', 'false_positive' => 'تعليم كإنذار كاذب'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <a class="ui-text-soft" href="{{ route('admin.security.index') }}">العودة إلى مركز القيادة</a>
        <h1 class="ui-title text-2xl font-bold mt-2">{{ $event->title }}</h1>
        <p class="ui-text-muted mt-1">بلاغ #{{ $event->id }} · {{ $event->event_code }}</p>
    </div>
    <span class="ui-badge {{ in_array($event->severity, ['high', 'critical'], true) ? 'ui-badge-danger' : 'ui-badge-warning' }}">{{ $statusLabels[$event->status] ?? $event->status }}</span>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-4">التقرير الميداني</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="ui-text-muted">التصنيف</dt><dd class="ui-text-soft mt-1">{{ $event->category }} / {{ $event->severity }} (ثقة {{ $event->confidence }}%)</dd></div>
                <div><dt class="ui-text-muted">عدد مرات الرصد</dt><dd class="ui-title mt-1">{{ number_format($event->occurrences) }}</dd></div>
                <div><dt class="ui-text-muted">المصدر المنقح</dt><dd class="ui-text-soft mt-1">{{ $event->masked_source_ip ?: 'غير متاح' }}</dd></div>
                <div><dt class="ui-text-muted">المسار</dt><dd class="ui-text-soft mt-1">{{ $event->http_method }} {{ $event->route ?: 'غير متاح' }}</dd></div>
                <div><dt class="ui-text-muted">أول رصد</dt><dd class="ui-text-soft mt-1">{{ $event->first_seen_at }}</dd></div>
                <div><dt class="ui-text-muted">آخر رصد</dt><dd class="ui-text-soft mt-1">{{ $event->last_seen_at }}</dd></div>
            </dl>
            @if($event->description)<div class="ui-alert ui-alert-info mt-5"><span class="ui-alert-body">{{ $event->description }}</span></div>@endif
        </section>

        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-4">الأدلة المنقحة</h2>
            <div class="space-y-2">
                @forelse($event->evidence ?? [] as $key => $value)
                    <div class="ui-list-row"><span class="ui-text-muted">{{ $key }}</span><span class="ui-text-soft">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span></div>
                @empty
                    <p class="ui-text-muted">لا توجد أدلة إضافية محفوظة.</p>
                @endforelse
            </div>
        </section>

        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-4">سجل الأوامر</h2>
            <div class="space-y-3">
                @forelse($event->activities->sortByDesc('created_at') as $activity)
                    <div class="ui-list-row"><div><strong class="ui-title">{{ $actionLabels[$activity->action] ?? $activity->action }}</strong><p class="ui-text-soft mt-1">{{ $activity->note ?: 'دون ملاحظة' }}</p></div><span class="ui-text-muted text-sm">{{ $activity->user?->name ?: 'النظام' }} · {{ $activity->created_at->diffForHumans() }}</span></div>
                @empty
                    <p class="ui-text-muted">لم يصدر أي أمر على هذا البلاغ بعد.</p>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="ui-card p-6 h-fit">
        <h2 class="ui-title text-xl font-semibold mb-2">إصدار أمر</h2>
        <p class="ui-text-soft mb-4">كل أمر يسجل باسمك وعنوان المصدر. اذكر السبب قبل الاحتواء أو الإغلاق.</p>
        <form method="POST" action="{{ route('admin.security.action', $event) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <label class="block"><span class="ui-field-label">الأمر</span><select class="ui-input w-full mt-1" name="action" required>@foreach($actionLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label class="block"><span class="ui-field-label">سبب الأمر أو نتيجة التحقق</span><textarea class="ui-input w-full mt-1" name="note" rows="5" maxlength="1000" placeholder="اكتب سببًا واضحًا؛ مطلوب للأوامر الحساسة."></textarea></label>
            <button class="ui-btn ui-btn-primary w-full justify-center" type="submit">تنفيذ الأمر وتوثيقه</button>
        </form>
    </aside>
</div>
@endsection
