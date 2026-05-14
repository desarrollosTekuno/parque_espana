<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Billing\Charge;
use Illuminate\Support\Facades\DB;
use App\Models\AdminClub\BusinessAd;
use App\Models\Billing\ChargeConcept;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;


class BusinessAdController extends Controller {

    public function __construct(){
        $this->middleware('permission:business-ads.index')->only('index');
        $this->middleware('permission:business-ads.approve')->only('approve');
        $this->middleware('permission:business-ads.reject')->only('reject');
    }

    public function index(Request $request){
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();
            $query = BusinessAd::with(['status', 'member', 'category'])->where('club_id', $clubId);
           // dd($query);
            if ($search = $request->input("search")) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                    ->orWhereHas('category', function($q) use ($search, $operator){
                        $q->where('name', $operator, "%{$search}%");
                    })
                    ->orWhereHas('member', function($u) use ($search, $operator){
                        $u->where('first_name', $operator, "%{$search}%")
                        ->orWhere('last_name', $operator, "%{$search}%");
                    });
                });
            }
            $query->orderBy('id', 'desc');
            //dd($request->all());
            $ads = $query->paginate(
                $request->input("per_page", 10)
            )->withQueryString();
            return Inertia::render('AdminClubs/BusinessAds/Index', [
                'ads' => $ads
            ]);
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('AdminClubs/BusinessAds/Index', [
                'ads' => [
                    'data' => [],
                    'total' => 0
                ],
                'messageError' => $e->getMessage()
            ]);
        }
    }

    /*   Aprobar anuncio   */
    public function approve($id){
        try {
            DB::beginTransaction();

            $ad = BusinessAd::with('member.accountMemberships.membershipAccount.memberships')
                ->findOrFail($id);
            $ad->update([
                'status_id' => 3, // approved
                'approved_at' => now()
            ]);

            if (!$ad->member) {
                throw new \Exception('El anuncio no tiene miembro asociado');
            }

            $accountMembership = $ad->member->accountMemberships()->first();
            if (!$accountMembership) {
                throw new \Exception('El usuario no tiene cuenta de membresía');
            }
            $membership = $accountMembership->membershipAccount->memberships->first();
            if (!$membership) {
                throw new \Exception('No se encontró membership');
            }
            $concept = ChargeConcept::query()
                ->with('clubAmounts')
                ->where('code', 'BUSINESS_AD')
                ->firstOrFail();
            $conceptAmount = $concept->resolveAmountForClub($ad->club_id);

            if ($conceptAmount === null) {
                throw new \Exception('El concepto de cobro para negocio/publicidad no tiene monto configurado.');
            }

            Charge::create([
                'membership_account_id' => $accountMembership->membership_account_id,
                'membership_id' => $membership->id ?? null,
                'member_id' => $ad->member_id,
                'concept_id' => $concept->id,
                'description' => $concept->description,
                'amount' => $conceptAmount,
                'balance' => $conceptAmount,
                'issue_date' => now(),
                'due_date' => now()->addDays(7),
                'period_year' => now()->year,
                'period_month' => now()->month,
                'allows_partial_payments' => false,
                'status' => 'pending',
                'metadata' => [
                    'business_ad_id' => $ad->id,
                    'club_id' => $ad->club_id,
                    'concept_amount_source' => 'club_or_default',
                ]
            ]);
            DB::commit();
            return back()->with('success','Anuncio aprobado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            //dd($e->getMessage());
            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }

    /*   Rechazar anuncio   */
    public function reject(Request $request, $id){
        try {
            DB::beginTransaction();

            $ad = BusinessAd::findOrFail($id);
            $ad->update([
                'status_id' => 2, // rejected
                'rejection_reason' => $request->reason,
            ]);
            DB::commit();
            return back()->with('success','Anuncio rechazado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }
}
