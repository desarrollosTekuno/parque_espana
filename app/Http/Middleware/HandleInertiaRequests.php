<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\AdminClub\BusinessAd;
use App\Models\Memberships\PendingAgeTransition;

use function Symfony\Component\Clock\now;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()
                    ? array_merge(
                        $request->user()->toArray(),
                        [
                            'two_factor_enabled' => $request->user()->two_factor_confirmed_at !== null,
                        ]
                    )
                    : null,

                'roles' => $request->user()
                    ? $request->user()->roles->pluck('name')
                    : [],
                // 'roles' => cache()->remember(
                //     "user_roles_{$request->user()->id}",
                //     Carbon::now()->addMinutes(10),
                //     fn () => $request->user()
                //         ? $request->user()->roles->pluck('name')
                //         : []
                // ),

                'permissions' => $request->user()
                    ? $request->user()
                        ->getAllPermissions()
                        ->load('contexts')
                        ->filter(fn($permission) => $permission->contexts->contains('value', 'web'))
                        ->pluck('name')
                        ->values()
                    : [],
                // 'permissions' => cache()->remember(
                //     "user_permissions_{$request->user()->id}",
                //     Carbon::now()->addMinutes(10),
                //     fn () => $request->user()
                //         ? $request->user()
                //             ->getAllPermissions()
                //             ->filter(function ($permission) {
                //                 return $permission->contexts->contains('value', 'web');
                //             })
                //             ->pluck('name')
                //             ->values()
                //         : []
                // )
            ],

            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'messageError' => fn () => $request->session()->get('messageError'),
                ];
            },

            'pendingBusinessAds' => function () use ($request) {
                $clubId = session('club_id');
                return BusinessAd::when($clubId, function ($query) use ($clubId) {
                        $query->where('club_id', $clubId);
                    })
                    ->where('status_id', 1)
                    ->count();
            },

            'pendingAgeTransitions' => function () use ($request) {
                $clubId = session('club_id');
                if (!$clubId || !$request->user()) return 0;
                return PendingAgeTransition::query()
                    ->whereHas('membership', fn ($q) => $q->where('club_id', $clubId))
                    ->where('status', 'pending')
                    ->count();
            },

            'showingMobileMenu' => false,
            
        ]);
    }
}
