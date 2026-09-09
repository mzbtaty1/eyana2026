<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class CheckStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من وجود المستخدم أولاً
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // التحقق من حالة المستخدم
        if ($user->status == 0) {
            Auth::logout();
            
            // إعادة التوجيه إلى صفحة تسجيل الدخول مع رسالة خطأ
            return redirect()->route('login')
                ->withErrors(['msg' => 'تم حظر حسابك، يرجى مراسلة المسئول لفك الحظر']);
        }

        return $next($request);
    }
}