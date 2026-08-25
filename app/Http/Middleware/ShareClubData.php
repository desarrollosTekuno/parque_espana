<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareClubData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {

            /** @var \App\Models\User $user */
            $user = Auth::user();

            $clubs = $user->clubs()
                ->select('clubs.id', 'clubs.name', 'clubs.logo_path')
                ->orderBy('clubs.name')
                ->get();

            if (!session()->has('club_id') && $clubs->count()) {
                session(['club_id' => $clubs->first()->id]);
            }

            Inertia::share([
                'auth.clubs' => $clubs,
                'auth.currentClub' => session('club_id')
            ]);
        }

        return $next($request);
        
    }
}