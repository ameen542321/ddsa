{{-- إصلاح مطبق: قالب اللوحة يحمل الثيم خارجيًا ويعتمد الهيدرات الفعلية فقط. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark ui-font-loading">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preload" href="{{ asset('fonts/cairo/cairo-arabic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Cairo-Regular.ttf') }}" as="font" type="font/ttf" crossorigin>
    {{-- يبقى تحميل الثيم متزامنًا في الرأس عمدًا لمنع وميض الوضع قبل رسم الصفحة. --}}
    <script src="{{ asset('js/dashboard-theme.js') }}"></script>

    {{-- محمل مكتبة مشترك وليس منطق صفحة مضمّنًا؛ تستهلك الوحدات المركزية window.Swal بعد تحميله. --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <title>@yield('title', 'CARLED Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="ui-page min-h-screen">
@php
    $role = auth('web')->check() ? (auth('web')->user()->role ?? 'user') : null;
    $usesAdminSidebar = $role === 'admin';
@endphp
@if($usesAdminSidebar)
    <div class="min-h-screen flex">
@endif
@if(auth('web')->check())

    @if($role === 'admin')
        @include('dashboard.sidebars.admin')
    @endif
@endif

    <div class="min-w-0 flex-1 flex flex-col">
        <div hidden
             data-ui-flash-messages
             data-success-message="{{ session('success') }}"
             data-error-message="{{ session('error') }}"
             data-warning-message="{{ session('warning') }}"
             data-info-message="{{ session('info') ?: session('status') }}"></div>
        <header>
@if(auth('accountant')->check())
    @include('dashboard.navbars.accountant')
@elseif(auth('web')->check())
    @php
        $role = auth('web')->user()->role ?? 'user';
    @endphp

    @if($role === 'admin')
        @include('dashboard.navbars.admin')
    @else
        @include('dashboard.navbars.user')
    @endif
@endif
        </header>
        <main class="flex-1 px-3 py-4 sm:px-6 sm:py-6">
            @if(session(\App\Services\SupportSessionService::SESSION_KEY))
                @php($technicalSupportSession = app(\App\Services\SupportSessionService::class)->active())
                @if($technicalSupportSession)
                    <div class="ui-alert ui-alert-warning mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" role="status">
                        <div>
                            <strong>جلسة دعم تقني نشطة</strong>
                            <span class="ui-text-soft">— التذكرة {{ $technicalSupportSession->ticket_reference }} — تعمل الآن بصفة {{ $technicalSupportSession->target instanceof \App\Models\Accountant ? 'المحاسب' : 'المالك' }}: {{ $technicalSupportSession->target?->name }} — تنتهي تلقائيًا {{ $technicalSupportSession->expires_at?->format('H:i') }}</span>
                        </div>
                        <form method="POST" action="{{ route('admin.support.stop') }}">
                            @csrf
                            <button type="submit" class="ui-btn ui-btn-danger w-full sm:w-auto">إنهاء جلسة الدعم</button>
                        </form>
                    </div>
                @endif
            @endif
            @if(auth('accountant')->check() && session('accountant_shift_gap_business_date'))
                <div class="mb-4 inline-flex flex-wrap items-center justify-center gap-2 rounded-xl border ui-border ui-status-success-bg px-3 py-2 ui-text-caption font-bold ui-status-success">
                    <span aria-label="وضع مراجعة الشفت مفعل" class="inline-flex items-center gap-1 rounded-full ui-status-success-bg ui-status-success px-2 py-1 font-black">
                        <i class="fa-solid fa-check-circle"></i> مفعل
                    </span>
                    <span aria-label="أي عملية جديدة ستسجل على تاريخ العمل المرجع" class="font-mono ui-title">{{ session('accountant_shift_gap_business_date') }}</span>
                    <form method="POST" action="{{ route('accountant.shift-gaps.clear') }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" aria-label="تأجيل الطلب والعودة لإدخال عمليات الشفت الحالي" class="ui-btn ui-btn-secondary rounded-lg px-2 py-1 ui-text-caption font-bold">
                            تأجيل
                        </button>
                    </form>
                </div>
            @endif
            @yield('content')
        </main>

        <footer class="mt-6">
            @include('dashboard.footer')
        </footer>

    </div>
@if($usesAdminSidebar)
    </div>
@endif
<x-ui.help-modal />
@yield('scripts')

</body>
</html>
