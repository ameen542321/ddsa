<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter; // لإدارة محاولات الدخول
use App\Models\User; // أو موديول المحاسب حسب الحاجة
use Illuminate\Support\Str;
use App\Services\SupportSessionService;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // ⭐ المفتاح الآن يعتمد على الإيميل فقط لضمان سهولة المسح
        $throttleKey = Str::lower($request->input('email'));

        // فحص الحالة قبل كل شيء
        $user = \App\Models\User::where('email', $request->email)->first()
                ?? \App\Models\Accountant::where('email', $request->email)->first();

        if ($user && $user->status === 'suspended') {
            return back()->withErrors(['email' => 'حسابك موقوف، راجع مالك المتجر.']);
        }

        // إذا كان نشطاً، نتأكد من تصفير أي قيود قديمة عالقة في الكاش
        if ($user && $user->status === 'active') {
            RateLimiter::clear($throttleKey);
        }

        // فحص عدد المحاولات
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->suspendUserAccount($request->input('email'));
            return back()->withErrors([
                'email' => 'لقد تم إيقاف حسابك لتكرار المحاولات الخاطئة. يرجى مراجعة المالك.'
            ]);
        }

        // محاولات الدخول
        if (Auth::guard('accountant')->attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            return $this->handleLoginSuccess($request, 'accountant');
        }

        if (Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            return $this->handleLoginSuccess($request, 'web');
        }

        // تسجيل فشل المحاولة
        RateLimiter::hit($throttleKey, 3600);

        return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة'])->onlyInput('email');
    }

    /**
     * دالة مخصصة لإيقاف الحساب في قاعدة البيانات
     */
    protected function suspendUserAccount($email)
    {
        // نبحث في جدول المستخدمين
        $user = \App\Models\User::where('email', $email)->first();
        if ($user) {
            $user->update(['status' => 'suspended']); // افترضنا أن العمود اسمه status
        }

        // نبحث أيضاً في جدول المحاسبين إذا كان النظام منفصلاً
        $accountant = \App\Models\Accountant::where('email', $email)->first();
        if ($accountant) {
            $accountant->update(['status' => 'suspended']);
        }
    }
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // public function login(Request $request)
    // {
    //     $credentials = $request->validate([
    //         'email'    => ['required', 'email'],
    //         'password' => ['required'],
    //     ]);

    //     $remember = $request->boolean('remember');

    //     // 1) محاولة دخول المحاسب
    //     if (Auth::guard('accountant')->attempt($credentials, $remember)) {
    //         return $this->handleLoginSuccess($request, 'accountant');
    //     }

    //     // 2) محاولة دخول المستخدم (مالك أو أدمن)
    //     if (Auth::guard('web')->attempt($credentials, $remember)) {
    //         return $this->handleLoginSuccess($request, 'web');
    //     }

    //     return back()
    //         ->withErrors(['email' => 'بيانات الدخول غير صحيحة'])
    //         ->onlyInput('email');
    // }

    /**
     * دالة موحدة للتعامل مع نجاح الدخول وتوجيه كل رتبة لمكانها
     */
    protected function handleLoginSuccess(Request $request, $guard)
    {
        // إغلاق الجلسات الأخرى لضمان عدم التداخل (Security Best Practice)
        if ($guard === 'accountant') {
            Auth::guard('web')->logout();
        } else {
            Auth::guard('accountant')->logout();
        }

        $request->session()->regenerate();

        $user = Auth::guard($guard)->user();

        // التوجيه الذكي بناءً على الرتبة والحارس
        if ($guard === 'accountant') {
            return redirect()->route('accountant.dashboard');
        }

        return ($user->role === 'admin')
            ? redirect()->route('admin.dashboard.index')
            : redirect()->route('user.dashboard');
    }

    /**
     * تسجيل الخروج الشامل (Universal Logout)
     */
    public function logout(Request $request)
    {
        $supportSessions = app(SupportSessionService::class);
        if ($supportSessions->active($request)) {
            $supportSessions->stop($request);
        }

        // تسجيل الخروج من كافة الحراس المتاحة
        Auth::guard('web')->logout();
        Auth::guard('accountant')->logout();
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'تم تسجيل الخروج بنجاح.');
    }
}
