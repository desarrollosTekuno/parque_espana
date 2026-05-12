<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Members\Act;
use App\Models\Members\Fine;
use App\Models\Billing\Charge;
use App\Models\Members\ActFile;
use App\Models\Members\Warning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;


class ActController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:acts.index')->only('index');
        $this->middleware('permission:acts.create')->only('store');
        $this->middleware('permission:acts.update')->only('update');
    }

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

            $act = Act::create([
                'account_id' => $request->account_id,
                'member_id' => $request->member_id,
                'club_id' => $request->club_id,

                'folio' => $request->folio,

                'violation_type' =>
                    $request->violation_type === 'otro'
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

                Charge::create([
                    'membership_account_id' => $request->account_id,
                    'membership_id' => $request->membership_id ?? null,
                    'member_id' => $request->member_id,
                    'concept_id' => '5', // ID fijo para multa por acta administrativa
                    'description' => 'Multa por acta administrativa',
                    'amount' => $request->amount,
                    'balance' => $request->amount,
                    'issue_date' => now(),
                    'due_date' => $request->due_date,
                    'period_year' => now()->year,
                    'period_month' => now()->month,
                    'allows_partial_payments' => false,
                    'status' => 'pending',
                    'metadata' => [
                        'fine_act_id' => $act->id,
                        'club_id' => $request->club_id,
                        'concept_amount_source' => 'Fine specified in act creation',
                    ]
                ]);
                DB::commit();
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
                        'mime_type' => $file->getMimeType()
                    ]);
                }
            }

            DB::commit();

            return back();

        } catch (\Exception $e) {

            DB::rollBack();

            report($e);

            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }

    public function update(Request $request, Act $act)
    {
        DB::beginTransaction();

        try {

            $act->update([
                'member_id' => $request->member_id,

                'violation_type' =>
                    $request->violation_type === 'otro'
                        ? $request->other_violation
                        : $request->violation_type,

                'description' => $request->description,
                'date' => $request->date,
                'time' => $request->time,
            ]);

            // MULTA
            if ($request->boolean('hasFine')) {

                $act->fine()->updateOrCreate(
                    [],
                    [
                        'amount' => $request->amount,
                        'concept' => $request->concept,
                        'due_date' => $request->due_date
                    ]
                );

                Charge::updateOrCreate(

                    // CAMPOS PARA BUSCAR
                    [
                        'membership_account_id' => $request->account_id,
                        'membership_id' => $request->membership_id,
                        'member_id' => $request->member_id,
                        'concept_id' => 5,
                    ],

                    // CAMPOS PARA ACTUALIZAR
                    [
                        'description' => 'Multa por acta administrativa',
                        'amount' => $request->amount,
                        'balance' => $request->amount,
                        'issue_date' => now(),
                        'due_date' => $request->due_date,
                        'period_year' => now()->year,
                        'period_month' => now()->month,
                        'allows_partial_payments' => false,
                        'status' => 'pending',

                        'metadata' => [
                            'fine_act_id' => $act->id,
                            'club_id' => $request->club_id,
                            'concept_amount_source' => 'Fine specified in act creation',
                        ]
                    ]
                );

            } else {

                $act->fine()->delete();

                Charge::where('metadata->fine_act_id', $act->id)
                    ->delete();
            }

            // ADVERTENCIA
            $act->warning()->updateOrCreate(
                [],
                [
                    'type' => $request->warning_type,
                    'has_suspension' => $request->has_suspension,
                    'suspension_start' => $request->suspension_start,
                    'suspension_end' => $request->suspension_end
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | ARCHIVOS
            |--------------------------------------------------------------------------
            */

            $existingFiles = $request->existing_files ?? [];

            // eliminar archivos borrados
            $filesToDelete = $act->files()
                ->whereNotIn('id', $existingFiles)
                ->get();

            foreach ($filesToDelete as $file) {

                Storage::disk('public')->delete($file->path);

                $file->delete();
            }

            // agregar nuevos
            if ($request->hasFile('files')) {

                foreach ($request->file('files') as $file) {

                    $path = $file->store('acts', 'public');

                    ActFile::create([
                        'act_id' => $act->id,
                        'path' => $path,
                        'mime_type' => $file->getMimeType()
                    ]);
                }
            }

            DB::commit();

            return back();

        } catch (\Exception $e) {

            DB::rollBack();

            report($e);

            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }
}
