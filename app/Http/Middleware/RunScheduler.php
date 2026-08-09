<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Artisan;

class RunScheduler
{
    public function handle($request, Closure $next)
    {
        // تشغيل الـ Scheduler في الخلفية
        Artisan::call('schedule:run');

        return $next($request);
    }
}
