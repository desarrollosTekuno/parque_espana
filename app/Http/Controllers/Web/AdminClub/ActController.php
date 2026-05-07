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
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;


class ActController extends Controller
{
    public function index(Request $request, $account_id)
    {
        $query = Act::where('account_id', $account_id)
            ->with(['fine', 'warning', 'files', 'account']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('folio', 'like', "%{$request->search}%")
                ->orWhere('violation_type', 'like', "%{$request->search}%")
                ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $acts = $query->latest()->paginate(10);
        $account = MembershipAccount::with('members')->find($account_id);
        $membership = Membership::where('membership_account_id', $account_id)->first();

        return Inertia::render('Members/Acts', [
            'acts' => $acts,
            'account' => $account,
            'account_id' => $account_id,
            'membershipId' => $membership,
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // ACTA
            $act = Act::create([
                'account_id' => $request->account_id,
                'member_id' => $request->member_id,
                'club_id' => $request->club_id,

                'folio' => $request->folio,
                'violation_type' => $request->violation_type === 'otro'
                    ? $request->other_violation
                    : $request->violation_type,

                'description' => $request->description,
                'date' => $request->date,
                'time' => $request->time,
            ]);
            
            // MULTA
            if ($request->hasFine) {
                Fine::create([
                    'act_id' => $act->id,
                    'amount' => $request->amount,
                    'concept' => $request->concept,
                    'due_date' => $request->due_date,
                ]);
            }

            // ADVERTENCIA
            if ($request->warning_type) {
                Warning::create([
                    'act_id' => $act->id,
                    'type' => $request->warning_type,
                    'has_suspension' => $request->has_suspension ?? false,
                    'suspension_start' => $request->suspension_start,
                    'suspension_end' => $request->suspension_end,
                ]);
            }

            // ARCHIVOS
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('acts', 'public');

                    ActFile::create([
                        'act_id' => $act->id,
                        'path' => $path,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('acts.index', $request->account_id);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()
                ->withErrors([
                    'messageError' => $e->getMessage()
                ])
                ->withInput();
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
