<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\PhysicalAd;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Members\Member;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PhysicalAdController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:physical-ads.index')->only('index');
        $this->middleware('permission:physical-ads.store')->only('store');
    }

    public function index(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');
        $driver = DB::getDriverName();
        $like   = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = PhysicalAd::with('member')
            ->where('club_id', $clubId);

        if ($search = $request->input('search')) {
            $query->whereHas('member', function ($q) use ($search, $like) {
                $q->where('first_name', $like, "%{$search}%")
                  ->orWhere('last_name', $like, "%{$search}%");
            });
        }

        $ads = $query->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('AdminClubs/BusinessAds/Index', [
            'physicalAds' => $ads,
        ]);
    }

    /**
     * Búsqueda de socios para el autocomplete del modal.
     */
    public function searchMembers(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');
        $search = $request->input('q', '');
        $driver = DB::getDriverName();
        $like   = $driver === 'pgsql' ? 'ilike' : 'like';

        $members = Member::with(['accountMemberships.membershipAccount'])
            ->byClub($clubId)
            ->where(function ($q) use ($search, $like) {
                $q->where('first_name', $like, "%{$search}%")
                  ->orWhere('last_name', $like, "%{$search}%");
            })
            ->limit(20)
            ->get()
            ->map(function (Member $m) {
                $accountMembership = $m->accountMemberships->first();
                return [
                    'id'                    => $m->id,
                    'full_name'             => $m->full_name,
                    'membership_account_id' => $accountMembership?->membership_account_id,
                ];
            });

        return response()->json($members);
    }

    /**
     * Crea el anuncio físico y el cargo correspondiente.
     */
    public function store(Request $request)
    {
        $request->validate([
            'member_id' => [
                'required',
                'integer',
                Rule::exists((new Member)->getConnectionName().'.'.(new Member)->getTable(), 'id'),
            ],
            'size' => ['required', 'in:carta,oficio,doble_carta,doble_oficio'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            $clubId    = session('club_id');
            $sizes     = PhysicalAd::SIZES;
            $unitPrice = $sizes[$request->size]['price'];
            $total     = $unitPrice * $request->quantity;

        
            $today    = Carbon::today();
            $startsAt = $today;
            $endsAt = $startsAt->copy()->addMonthNoOverflow();

            // Cuenta del socio
            $member = Member::with('accountMemberships.membershipAccount.memberships')
                ->findOrFail($request->member_id);

            $accountMembership = $member->accountMemberships()->first();
            if (!$accountMembership) {
                throw new \Exception('El socio no tiene una cuenta de membresía activa.');
            }

            $membership = $accountMembership->membershipAccount->memberships->first();

            // Registro del anuncio físico
            $physicalAd = PhysicalAd::create([
                'club_id'               => $clubId,
                'member_id'             => $member->id,
                'membership_account_id' => $accountMembership->membership_account_id,
                'size'                  => $request->size,
                'quantity'              => $request->quantity,
                'amount'                => $total,
                'starts_at'             => $startsAt,
                'ends_at'               => $endsAt,
                'status'                => 'pending_payment',
                'notes'                 => $request->notes,
            ]);

            // Concepto de cobro
            $concept = ChargeConcept::where('code', 'PHYSICAL_AD')->firstOrFail();

            Charge::create([
                'membership_account_id' => $accountMembership->membership_account_id,
                'membership_id'         => $membership?->id,
                'member_id'             => $member->id,
                'concept_id'            => $concept->id,
                'description'           => $this->buildDescription($request->size, $request->quantity),
                'amount'                => $total,
                'balance'               => $total,
                'issue_date'            => now(),
                'due_date'              => now(),
                'period_year'           => $startsAt->year,
                'period_month'          => $startsAt->month,
                'allows_partial_payments' => false,
                'status'                => 'pending',
                'metadata'              => [
                    'physical_ad_id' => $physicalAd->id,
                    'club_id'        => $clubId,
                    'size'           => $request->size,
                    'quantity'       => $request->quantity,
                    'unit_price'     => $unitPrice,
                ],
            ]);

            DB::commit();

            return back()->with('success', 'Anuncio físico registrado correctamente. El socio deberá presentarse a firmar el formato.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    private function buildDescription(string $size, int $quantity): string
    {
        $label = PhysicalAd::SIZES[$size]['label'];
        return $quantity > 1
            ? "Anuncio físico {$label} x{$quantity}"
            : "Anuncio físico {$label}";
    }
}
