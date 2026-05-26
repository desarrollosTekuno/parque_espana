<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\ReservationGuestList;
use App\Models\Administrator\Club;
use App\Models\Billing\ClubPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class GuestListPaymentController extends Controller {

    public function __construct()
    {
        $this->middleware('permission:guest-list-payments.index')->only('index');
    }

    public function index(Request $request) {

        $clubId = $request->club_id ?? session('club_id');

        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'guestListPayments';

        $query = ReservationGuestList::with([
                'guestListItems' => fn ($q) => $q->notBillableNotComped(),
                'member'
            ])
            ->whereHas('guestListItems', function ($q) {
                $q->notBillableNotComped();
            })
            ->where('club_id', $clubId);
            // ->where('status', ReservationGuestList::APPROVED);

        if ($search = $request->input("{$prefix}_search")) {

            $query->where(function ($q) use ($driver, $search) {

                $q->where('title', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                    // ->orWhere('total_guests', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    // ->orWhere('billable_subtotal', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    // ->orWhere('discount', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    // ->orWhere('total', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $guestListPayments = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/GuestListPayments/Index', [
            'guestListPayments' => $guestListPayments,
            'clubPaymentMethods' => $this->resolveClubPaymentMethods(),
        ]);
    }

    public function transformData($query) {

    }

    protected function resolveClubPaymentMethods()
    {

        $clubId = $request->club_id ?? session('club_id');

        return ClubPaymentMethod::query()
            ->with('paymentMethod')
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(function ($clubPaymentMethod) {
                return [
                    'id' => $clubPaymentMethod->paymentMethod?->id,
                    'code' => $clubPaymentMethod->paymentMethod?->code,
                    'name' => $clubPaymentMethod->paymentMethod?->name,
                    'requires_reference' => (bool) $clubPaymentMethod->paymentMethod?->requires_reference,
                    'requires_bank_name' => (bool) $clubPaymentMethod->paymentMethod?->requires_bank_name,
                    'requires_check_number' => (bool) $clubPaymentMethod->paymentMethod?->requires_check_number,
                    'affects_cash_cut' => (bool) $clubPaymentMethod->paymentMethod?->affects_cash_cut,
                ];
            })
            ->filter(fn ($method) => !empty($method['id']))
            ->values();
    }
}
