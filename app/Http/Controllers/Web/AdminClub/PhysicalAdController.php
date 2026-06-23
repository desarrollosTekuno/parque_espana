<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\PhysicalAd;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        $query = PhysicalAd::with('member')->where('club_id', $clubId);

        if ($search = $request->input('search')) {
            $query->whereHas('member', function ($q) use ($search, $like) {
                $q->where('first_name', $like, "%{$search}%")
                  ->orWhere('last_name', $like, "%{$search}%");
            });
        }

        $ads = $query->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return \Inertia\Inertia::render('AdminClubs/BusinessAds/Index', [
            'physicalAds' => $ads,
        ]);
    }

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

    public function store(Request $request)
    {
        $clubId = (int) session('club_id');

        $request->validate([
            'member_id'     => ['required', 'integer',
                Rule::exists((new Member)->getConnectionName().'.'.(new Member)->getTable(), 'id')],
            'size'          => ['required', Rule::in(array_keys(PhysicalAd::SIZES))],
            'quantity'      => ['required', 'integer', 'min:1', 'max:99'],
            'signed_format' => ['required', 'boolean'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            $unitPrice  = PhysicalAd::SIZES[$request->size]['price'];
            $total      = $unitPrice * $request->quantity;

            $startsAt = Carbon::today();
            $endsAt = $startsAt->copy()->addMonthNoOverflow();

            $member = Member::with('accountMemberships.membershipAccount.memberships')
                ->findOrFail($request->member_id);

            $accountMembership = $member->accountMemberships()->first();
            if (!$accountMembership) {
                throw new \Exception('El socio no tiene una cuenta de membresía activa.');
            }

            $membership = $accountMembership->membershipAccount->memberships->first();

            // 1. Anuncio físico
            $physicalAd = PhysicalAd::create([
                'club_id'               => $clubId,
                'member_id'             => $member->id,
                'membership_account_id' => $accountMembership->membership_account_id,
                'size'                  => $request->size,
                'quantity'              => $request->quantity,
                'amount'                => $total,
                'starts_at'             => $startsAt,
                'ends_at'               => $endsAt,
                'status'                => 'active',
                'signed_format'         => $request->signed_format,
                'notes'                 => $request->notes,
            ]);

            // 2. Cargo — directamente pagado (balance 0)
            $concept = ChargeConcept::where('code', 'PHYSICAL_AD')->firstOrFail();

            $charge = Charge::create([
                'membership_account_id'   => $accountMembership->membership_account_id,
                'membership_id'           => $membership?->id,
                'member_id'               => $member->id,
                'concept_id'              => $concept->id,
                'description'             => $this->buildDescription($request->size, $request->quantity),
                'amount'                  => $total,
                'balance'                 => 0,
                'issue_date'              => now(),
                'due_date'                => now(),
                'period_year'             => $startsAt->year,
                'period_month'            => $startsAt->month,
                'allows_partial_payments' => false,
                'status'                  => 'paid',
                'metadata'                => [
                    'physical_ad_id' => $physicalAd->id,
                    'club_id'        => $clubId,
                    'size'           => $request->size,
                    'quantity'       => $request->quantity,
                    'unit_price'     => $unitPrice,
                ],
            ]);

            // 3. Pago en efectivo
            $cashMethod = PaymentMethod::where('code', 'CASH')->where('is_active', true)->firstOrFail();

            $payment = Payment::create([
                'membership_account_id' => $accountMembership->membership_account_id,
                'club_id'               => $clubId,
                'payment_method_id'     => $cashMethod->id,
                'amount'                => $total,
                'paid_at'               => now(),
                'received_by'           => $request->user()?->id,
                'status'                => 'registered',
                'metadata'              => [
                    'physical_ad_id'     => $physicalAd->id,
                    'session_club_id'    => $clubId,
                    'settlement_channel' => 'cashier',
                    'affects_cash_cut'   => true,
                ],
            ]);

            // 4. Aplicación del pago al cargo
            PaymentApplication::create([
                'payment_id'     => $payment->id,
                'charge_id'      => $charge->id,
                'applied_amount' => $total,
            ]);

            DB::commit();

            return back()->with('success', 'Anuncio físico registrado correctamente.');
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
