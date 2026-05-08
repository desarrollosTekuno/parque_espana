<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Members\LockerAssignment;
use App\Models\Members\Locker;
use Illuminate\Http\Request;
use App\Models\Billing\Charge;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LockerController extends Controller {

    public function available(Request $request)
    {
        try {

            $prefix = 'lockers';
            $driver = DB::getDriverName();

            $query = Locker::query()
                ->with(['lockerAssignment.member'])
                ->where('club_id', $request->club_id)
                ->where('status', 'disponible');

            // filtro categoría
            if ($request->category) {
                $query->where('category', $request->category);
            }

            // buscador
            if ($search = $request->input("{$prefix}_search")) {

                $operator = $driver === 'pgsql'
                    ? 'ilike'
                    : 'like';

                $query->where(function ($q) use ($search, $operator) {

                    $q->where('number', $operator, "%{$search}%")
                    ->orWhere('category', $operator, "%{$search}%");
                });
            }

            // ordenamiento
            $sort = $request->input("{$prefix}_sort", 'number');
            $order = $request->input("{$prefix}_order", 'asc');

            $query->orderBy($sort, $order);

            // paginación
            $lockers = $query->paginate(
                $request->input("{$prefix}_per_page", 30)
            )->appends($request->except('club_id'));

            return response()->json($lockers);

        } catch (\Exception $e) {

            report($e);

            return response()->json([
                'message' => 'Error al cargar casilleros',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function assignedByAccount(Request $request)
    {
        $active = LockerAssignment::with(['locker', 'member'])
            ->whereHas('member', function ($q) use ($request) {

                $q->whereIn('id', function ($sub) use ($request) {

                    $sub->select('member_id')
                        ->from('memberships.account_members')
                        ->where('membership_account_id', $request->account_id);
                });
            })
            ->where('year', now()->year)
            ->get()
            ->map(function ($item) {

                $item->status = 'activo';

                return $item;
            });

        $pending = Charge::with('member')
            ->where('membership_account_id', $request->account_id)
            ->where('status', 'pending')
            ->get()
            ->map(function ($item) {

                $lockerId = $item->metadata['locker_id'] ?? null;

                if (!$lockerId) {
                    return null;
                }

                $locker = Locker::find($lockerId);

                return (object)[
                    'id' => $item->id,
                    'status' => 'pago_pendiente',
                    'locker' => $locker,
                    'member' => $item->member,
                ];
            })
            ->filter()
            ->values();

        return $active->concat($pending)->values();
    }

    public function cancel($id)
    {
        DB::beginTransaction();

        try {

            $charge = Charge::findOrFail($id);
            Locker::where(
                'id',
                $charge->metadata['locker_id']
            )->update([
                'status' => 'disponible'
            ]);

            $charge->delete();

            DB::commit();

            return back()->with(
                'success',
                'Solicitud cancelada correctamente'
            );

        } catch (\Exception $e) {

            DB::rollBack();

            report($e);

            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }
}
