<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserPaid
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->has_paid) {
            return redirect()->route('products')->with('error', 'You need to purchase a subscription to access this area.');
        }

        return $next($request);
    }
}
