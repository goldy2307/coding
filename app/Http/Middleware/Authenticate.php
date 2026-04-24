<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Determine which guard to use
        $guard = $request->expectsJson() || $request->is('api/*') ? 'sanctum' : 'web';

        if (!Auth::guard($guard)->check()) {
            if ($guard === 'sanctum') {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => ['Missing or invalid Authorization token']
                ], 200);
            }

            return redirect()->route('login');
        }

        

        // Optionally set the authenticated user globally
        Auth::setUser(Auth::guard($guard)->user());

        return $next($request);
    }
}
