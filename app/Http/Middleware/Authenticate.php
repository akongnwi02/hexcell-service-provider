<?php

namespace App\Http\Middleware;

use App\Exceptions\UnAuthorizationException;
use Closure;
use Illuminate\Support\Facades\Hash;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     * @throws UnAuthorizationException
     */
    public function handle($request, Closure $next)
    {
        if (! Hash::check($request->header('x-api-key'), config('app.api_key'))) {
            throw new UnAuthorizationException('Security Violation', 401);
        }
        return $next($request);
    }
}
