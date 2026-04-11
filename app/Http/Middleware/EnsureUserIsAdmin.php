<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $role = (string) ($user->role ?? '');

        if (! in_array($role, ['admin', 'operator', 'ops'], true)) {
            return redirect()->route('dashboard')->with('error', 'Access denied. Administrator role required.');
        }

        return $next($request);
    }
}
