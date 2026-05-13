<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Exports\CancellationHistoryExport;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CancellationHistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:members.cancellations.index');
    }

    public function index(Request $request)
    {
        $clubId = session('club_id');
        $prefix = 'cancellations';
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = $this->buildQuery($request, $clubId, $like, $prefix);

        $sortMap = [
            'id'               => 'id',
            'membership_number' => 'membership_number',
            'cancelled_at'     => 'cancelled_at',
        ];

        $sortColumn = $sortMap[$request->input("{$prefix}_sort", 'cancelled_at')] ?? 'cancelled_at';
        $order      = $request->input("{$prefix}_order", 'desc') === 'asc' ? 'asc' : 'desc';

        $cancellations = $query
            ->orderBy($sortColumn, $order)
            ->paginate(
                $request->input("{$prefix}_per_page", 15),
                ['*'],
                "{$prefix}_page",
                $request->input("{$prefix}_page", 1)
            )
            ->through(fn (MembershipAccount $account) => $this->formatAccount($account))
            ->appends($request->all());

        $processorIds = MembershipAccount::query()
            ->where('status', 'cancelled')
            ->whereNotNull('cancelled_by')
            ->whereHas('memberships', fn (Builder $q) => $q->where('club_id', $clubId)->where('is_primary', true))
            ->pluck('cancelled_by')
            ->unique();

        $processors = User::whereIn('id', $processorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Members/CancellationHistory', [
            'cancellations' => $cancellations,
            'processors'    => $processors,
            'filters'       => $request->only([
                "{$prefix}_search",
                "{$prefix}_type",
                "{$prefix}_from",
                "{$prefix}_to",
                "{$prefix}_processed_by",
            ]),
        ]);
    }

    public function letterUrl(MembershipAccount $account)
    {
        $clubId = session('club_id');

        $belongsToClub = $account->memberships()
            ->where('club_id', $clubId)
            ->where('is_primary', true)
            ->exists();

        if (!$belongsToClub) {
            abort(403);
        }

        if (!$account->cancellation_letter_path) {
            abort(404);
        }

        $url = Storage::disk('spaces')->temporaryUrl(
            $account->cancellation_letter_path,
            now()->addMinutes(10)
        );

        return response()->json(['url' => $url]);
    }

    public function export(Request $request)
    {
        $clubId = session('club_id');
        $driver = DB::getDriverName();
        $like   = $driver === 'pgsql' ? 'ilike' : 'like';

        $records = $this->buildQuery($request, $clubId, $like, 'cancellations')
            ->orderBy('cancelled_at', 'desc')
            ->get()
            ->map(fn (MembershipAccount $account) => $this->formatAccount($account));

        $filename = 'historial-bajas-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new CancellationHistoryExport($records), $filename);
    }

    private function buildQuery(Request $request, int $clubId, string $like, string $prefix): Builder
    {
        $query = MembershipAccount::query()
            ->with([
                'primaryHolder.member',
                'memberships' => fn ($q) => $q->with(['membershipType'])->where('is_primary', true),
                'cancelledBy',
            ])
            ->where('status', 'cancelled')
            ->whereHas('memberships', fn (Builder $q) => $q->where('club_id', $clubId)->where('is_primary', true));

        if ($search = $request->input("{$prefix}_search")) {
            $query->where(function (Builder $b) use ($search, $like) {
                $b->where('membership_number', $like, "%{$search}%")
                    ->orWhereHas('primaryHolder.member', fn (Builder $mq) => $mq
                        ->where('first_name', $like, "%{$search}%")
                        ->orWhere('last_name', $like, "%{$search}%")
                        ->orWhere('second_last_name', $like, "%{$search}%")
                        ->orWhere('email', $like, "%{$search}%")
                    );
            });
        }

        if ($type = $request->input("{$prefix}_type")) {
            $query->where('cancellation_type', $type);
        }

        if ($from = $request->input("{$prefix}_from")) {
            $query->whereDate('cancelled_at', '>=', Carbon::parse($from)->toDateString());
        }

        if ($to = $request->input("{$prefix}_to")) {
            $query->whereDate('cancelled_at', '<=', Carbon::parse($to)->toDateString());
        }

        if ($processedBy = $request->input("{$prefix}_processed_by")) {
            $query->where('cancelled_by', (int) $processedBy);
        }

        return $query;
    }

    private function formatAccount(MembershipAccount $account): array
    {
        $holder            = $account->primaryHolder?->member;
        $primaryMembership = $account->memberships->first();

        return [
            'id'                   => $account->id,
            'membership_id'        => $primaryMembership?->id,
            'membership_number'    => $account->membership_number,
            'holder_name'          => trim(collect([
                $holder?->first_name,
                $holder?->last_name,
                $holder?->second_last_name,
            ])->filter()->implode(' ')),
            'email'                => $holder?->email,
            'membership_type_name' => $primaryMembership?->membershipType?->name,
            'cancellation_type'    => $account->cancellation_type,
            'cancelled_at'         => $account->cancelled_at?->toIso8601String(),
            'cancelled_by_name'    => $account->cancelledBy?->name,
            'cancelled_by_id'      => $account->cancelled_by,
            'has_letter'           => !empty($account->cancellation_letter_path),
        ];
    }
}
