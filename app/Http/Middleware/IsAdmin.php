<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * [تعديل آمن] التحقق من أن المستخدم ضمن حارس web ويحمل دور admin.
     *
     * الهدف: حماية مسارات الإدارة دون تغيير أي ربط قائم في الراوتات.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('web')->user();

        // [تعديل آمن] إذا لم يكن المستخدم أدمن، يتم تحويله لصفحة no-access إن كانت متاحة.
        if (! $user || $user->role !== 'admin') {
            return redirect()->route('no.access');
        }

        return $next($request);
    }
}
