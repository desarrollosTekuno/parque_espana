<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Mail\DayPassTicketMail;
use App\Models\AdminClub\DayPass;
use App\Models\AdminClub\DayPassVisitor;
use App\Models\AdminClub\GuestListVariable;
use App\Models\AdminClub\VisitorIncident;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DayPassController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:day-passes.index')->only('index');
        $this->middleware('permission:day-passes.store')->only('store');
        $this->middleware('permission:day-passes.incidents.index')->only('indexIncidents');
        $this->middleware('permission:day-passes.incidents.store')->only('storeIncident');
    }

    public function index(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');
        $driver = DB::getDriverName();
        $like   = $driver === 'pgsql' ? 'ilike' : 'like';
        $prefix = 'dayPasses';

        $query = DayPass::with(['member', 'visitors', 'paymentMethod'])
            ->where('club_id', $clubId);

        if ($search = $request->input("{$prefix}_search")) {
            $query->whereHas('member', function ($q) use ($search, $like) {
                $q->where('first_name', $like, "%{$search}%")
                  ->orWhere('last_name', $like, "%{$search}%");
            });
        }

        $sort  = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');
        $query->orderBy($sort, $order);

        $dayPasses = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        $guestListVariables = GuestListVariable::where('club_id', $clubId)
            ->get()
            ->mapWithKeys(fn ($v) => [$v->code => (float) $v->value]);

        return Inertia::render('AdminClubs/DayPasses/Index', [
            'dayPasses'          => $dayPasses,
            'clubPaymentMethods' => $this->resolveClubPaymentMethods($clubId),
            'guestListVariables' => $guestListVariables,
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
                    'email'                 => $m->user?->email,
                ];
            });

        return response()->json($members);
    }

    public function checkIncidents(Request $request)
    {
        $firstName = trim($request->input('first_name', ''));
        $lastName  = trim($request->input('last_name', ''));
        $clubId = $request->club_id ?? session('club_id');

        if (!$firstName || !$lastName) {
            return response()->json([
                'has_incidents' => false,
                'incidents' => []
            ]);
        }

        $incidents = VisitorIncident::query()
        ->whereRaw('LOWER(unaccent(visitor_first_name)) = LOWER(unaccent(?))', [$firstName])
        ->whereRaw('LOWER(unaccent(visitor_last_name)) = LOWER(unaccent(?))', [$lastName])
        ->where('club_id', $clubId)
        ->latest()
        ->get([
            'id',
            'incident_type',
            'description',
            'charged_amount',
            'created_at'
        ]);

        return response()->json([
            'has_incidents' => $incidents->isNotEmpty(),
            'incidents'     => $incidents,
        ]);
    }

    public function store(Request $request)
    {
        $clubId = (int) session('club_id');

        $request->validate([
            'member_id'              => ['required', 'integer'],
            'date'                   => ['required', 'date'],
            'visitors'               => ['required', 'array', 'min:1'],
            'visitors.*.first_name'  => ['required', 'string', 'max:200'],
            'visitors.*.last_name'   => ['required', 'string', 'max:200'],
            'visitors.*.phone'       => ['required', 'string', 'max:20'],
            'visitors.*.age'         => ['required', 'integer', 'min:0', 'max:120'],
            'payment_method_id'      => ['required', 'integer'],
            'paid_at'                => ['required', 'date'],
            'reference'              => ['nullable', 'string', 'max:200'],
            'bank_name'              => ['nullable', 'string', 'max:200'],
            'check_number'           => ['nullable', 'string', 'max:100'],
            'notes'                  => ['nullable', 'string', 'max:500'],
        ]);

        $normalPrice  = GuestListVariable::where('code', 'NORMAL_PRICE')->where('club_id', $clubId)->value('value');
        $specialPrice = GuestListVariable::where('code', 'SPECIAL_PRICE')->where('club_id', $clubId)->value('value');
        $maxGuests    = GuestListVariable::where('code', 'MAX_GUESTS')->where('club_id', $clubId)->value('value');

        if (is_null($normalPrice) || is_null($specialPrice) || is_null($maxGuests)) {
            return back()->withErrors(['messageError' => 'El club no tiene configuradas las variables de pases de invitados.']);
        }

        if (count($request->visitors) > (int) $maxGuests) {
            return back()->withErrors(['messageError' => "El máximo de visitantes permitido es {$maxGuests}."]);
        }

        $totalAmount = 0;
        $visitors = collect($request->visitors)->map(function ($v) use ($normalPrice, $specialPrice, &$totalAmount) {
            $price = (int) $v['age'] >= 7 ? (float) $normalPrice : (float) $specialPrice;
            $totalAmount += $price;
            return array_merge($v, ['price' => $price, 'ticket_code' => (string) Str::uuid()]);
        });

        try {
            DB::beginTransaction();

            $member = Member::with(['accountMemberships.membershipAccount.memberships', 'user'])
                ->findOrFail($request->member_id);

            $accountMembership = $member->accountMemberships()->first();
            if (! $accountMembership) {
                throw new \Exception('El socio no tiene una cuenta de membresía activa.');
            }

            $membership = $accountMembership->membershipAccount->memberships->first();

            $dayPass = DayPass::create([
                'club_id'           => $clubId,
                'member_id'         => $member->id,
                'date'              => $request->date,
                'total_visitors'    => $visitors->count(),
                'amount'            => $totalAmount,
                'payment_method_id' => $request->payment_method_id,
                'paid_at'           => $request->paid_at,
                'reference'         => $request->reference,
                'bank_name'         => $request->bank_name,
                'check_number'      => $request->check_number, 
                'notes'             => $request->notes,
                'created_by'        => auth()->id(),
            ]);

            foreach ($visitors as $v) {
                DayPassVisitor::create([
                    'day_pass_id' => $dayPass->id,
                    'first_name'  => $v['first_name'],
                    'last_name'   => $v['last_name'],
                    'phone'       => $v['phone'],
                    'age'         => $v['age'],
                    'price'       => $v['price'],
                    'ticket_code' => $v['ticket_code'],
                ]);
            }

            $concept = ChargeConcept::where('code', 'GUEST_LIST')->firstOrFail();

            $charge = Charge::create([
                'membership_account_id'   => $accountMembership->membership_account_id,
                'membership_id'           => $membership?->id,
                'member_id'               => $member->id,
                'concept_id'              => $concept->id,
                'description'             => "Pase por día — {$request->date} — {$visitors->count()} visitante(s)",
                'amount'                  => $totalAmount,
                'balance'                 => 0,
                'issue_date'              => now(),
                'due_date'                => now(),
                'allows_partial_payments' => false,
                'status'                  => 'paid',
                'metadata'                => [
                    'charge_origin' => 'day_pass',
                    'concept_code'  => $concept->code,
                    'day_pass_id'   => $dayPass->id,
                    'club_id'       => $clubId,
                ],
            ]);

            $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

            $payment = Payment::create([
                'membership_account_id' => $accountMembership->membership_account_id,
                'club_id'               => $clubId,
                'payment_method_id'     => $paymentMethod->id,
                'amount'                => $totalAmount,
                'paid_at'               => $request->paid_at,
                'reference'             => $request->reference,
                'bank_name'             => $request->bank_name,
                'check_number'          => $request->check_number,
                'notes'                 => $request->notes,
                'received_by'           => auth()->id(),
                'status'                => 'registered',
                'metadata'              => [
                    'day_pass_id'        => $dayPass->id,
                    'session_club_id'    => $clubId,
                    'settlement_channel' => 'cashier',
                    'affects_cash_cut'   => $paymentMethod->affects_cash_cut,
                ],
            ]);

            PaymentApplication::create([
                'payment_id'     => $payment->id,
                'charge_id'      => $charge->id,
                'applied_amount' => $totalAmount,
            ]);

            DB::commit();

            // Envío del ticket por correo al socio
            if ($member->user?->email) {
                try {
                    $dayPass->load('visitors');
                    $dayPass->update(['ticket_sent_at' => now()]);
                    Mail::to($member->user->email)->send(new DayPassTicketMail($dayPass, $member));
                } catch (\Exception $e) {
                    Log::warning('No se pudo enviar el ticket de pase por día.', [
                        'day_pass_id' => $dayPass->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            return back()->with('success', 'Pase por día registrado y ticket enviado al socio correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }
    public function indexIncidents(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');
        $driver = DB::getDriverName();
        $like   = $driver === 'pgsql' ? 'ilike' : 'like';
        $prefix = 'incidents';

        $query = VisitorIncident::with(['recordedBy'])
            ->where('club_id', $clubId);

        if ($search = $request->input("{$prefix}_search")) {
            $query->where(function ($q) use ($search, $like) {
                $q->where('visitor_first_name', $like, "%{$search}%")
                  ->orWhere('visitor_last_name',  $like, "%{$search}%")
                  ->orWhere('visitor_phone',       $like, "%{$search}%")
                  ->orWhere('incident_type',       $like, "%{$search}%");
            });
        }

        $sort  = $request->input("{$prefix}_sort",  'id');
        $order = $request->input("{$prefix}_order", 'desc');
        $query->orderBy($sort, $order);

        $incidents = $query->paginate(
            $request->input("{$prefix}_per_page", 15),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/DayPasses/Incidents', [
            'incidents' => $incidents,
        ]);
    }

    public function storeIncident(Request $request)
    {
        $clubId = (int) session('club_id');

        $request->validate([
            'day_pass_visitor_id' => ['required', 'integer'],
            'incident_type'       => ['required', 'string', 'max:200'],
            'description'         => ['required', 'string', 'max:2000'],
            'charged_amount'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $visitor = DayPassVisitor::findOrFail($request->day_pass_visitor_id);

        VisitorIncident::create([
            'club_id'             => $clubId,
            'day_pass_visitor_id' => $visitor->id,
            'visitor_first_name'  => $visitor->first_name,
            'visitor_last_name'   => $visitor->last_name,
            'visitor_phone'       => $visitor->phone,
            'incident_type'       => $request->incident_type,
            'description'         => $request->description,
            'charged_amount'      => $request->charged_amount,
            'recorded_by'         => auth()->id(),
        ]);

        return back()->with('success', 'Incidente registrado correctamente.');
    }

    protected function resolveClubPaymentMethods(int $clubId)
    {
        return ClubPaymentMethod::query()
            ->with('paymentMethod')
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(function ($cpm) {
                return [
                    'id'                    => $cpm->paymentMethod?->id,
                    'code'                  => $cpm->paymentMethod?->code,
                    'name'                  => $cpm->paymentMethod?->name,
                    'requires_reference'    => (bool) $cpm->paymentMethod?->requires_reference,
                    'requires_bank_name'    => (bool) $cpm->paymentMethod?->requires_bank_name,
                    'requires_check_number' => (bool) $cpm->paymentMethod?->requires_check_number,
                    'affects_cash_cut'      => (bool) $cpm->paymentMethod?->affects_cash_cut,
                ];
            })
            ->filter(fn ($m) => !empty($m['id']))
            ->values();
    }
}
