<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Members\Act;
use App\Models\Members\Fine;
use App\Models\Members\ActFile;
use App\Models\Members\Warning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Memberships\MembershipAccount;
use Inertia\Inertia;


class ActController extends Controller
{
     public function index(Request $request)
    {
        $query = Act::query()
            ->with(['fine', 'warning', 'files', 'account']);
    
        // búsqueda
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('folio', 'like', "%{$request->search}%")
                  ->orWhere('violation_type', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        // filtro advertencia
        if ($request->warning_type) {
            $query->whereHas('warning', function ($q) use ($request) {
                $q->where('type', $request->warning_type);
            });
        }

        // filtro multa
        if ($request->has_fine !== null) {
            $request->has_fine
                ? $query->has('fine')
                : $query->doesntHave('fine');
        }

        // filtro por socio (IMPORTANTE para tu flujo)
        if ($request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        $acts = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('Members/Acts', [
            'acts' => $acts,
            'filters' => $request->only(['search', 'warning_type', 'has_fine']),
            'account' => $request->account_id
            ? MembershipAccount::with('members')->find($request->account_id)
            : null
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $folio = 'ACT-' . now()->format('Ymd') . '-' . str_pad(
                Act::count() + 1,
                4,
                '0',
                STR_PAD_LEFT
            );
            $act = Act::create([
                'folio' => $folio,
                'member_id' => $request->member_id,
                'account_id' => $request->account_id,
                'club_id' => $request->club_id,
                'violation_type' => $request->violation_type,
                'description' => $request->description,
                'date' => $request->date,
                'time' => $request->time,
            ]);
            if ($request->has_fine) {
                Fine::create([
                    'act_id' => $act->id,
                    'amount' => $request->amount,
                    'concept' => $request->concept,
                    'due_date' => $request->due_date,
                ]);
            }
            if ($request->has_suspension) {
                Warning::create([
                    'act_id' => $act->id,
                    'type' => $request->warning_type,
                    'has_suspension' => $request->has_suspension,
                    'suspension_start' => $request->suspension_start,
                    'suspension_end' => $request->suspension_end,
                ]);
            }
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('acts', 'public');

                    ActFile::create([
                        'act_id' => $act->id,
                        'path' => $path
                    ]);
                }
            }

            DB::commit();

            return back()->with('success', 'Acta creada correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(Request $request, Act $act)
    {
        DB::beginTransaction();

        try {

            $act->update([
                'member_id' => $request->member_id,
                'violation_type' => $request->violation_type,
                'description' => $request->description,
                'date' => $request->date,
                'time' => $request->time,
            ]);

            // multa
            if ($request->hasFine) {
                $act->fine()->updateOrCreate([], [
                    'amount' => $request->amount,
                    'concept' => $request->concept,
                    'due_date' => $request->due_date
                ]);
            } else {
                $act->fine()?->delete();
            }

            // advertencia
            $act->warning()->updateOrCreate([], [
                'type' => $request->warning_type,
                'has_suspension' => $request->has_suspension,
                'suspension_start' => $request->suspension_start,
                'suspension_end' => $request->suspension_end
            ]);

            DB::commit();

            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
