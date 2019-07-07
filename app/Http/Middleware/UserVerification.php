<?php

namespace App\Http\Middleware;

use Closure;
use Response; 

class UserVerification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $verified_at = $request->user()->phone_verified_at;
        if(is_null($verified_at)){
            return Response::json([
                'status' => 'error',
                'status_code' => 401,
                'message' => 'User not verified.',
                'data' => ''
            ]);
        }
        return $next($request);
    }
}
