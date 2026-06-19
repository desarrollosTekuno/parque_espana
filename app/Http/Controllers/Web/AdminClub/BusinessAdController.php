<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Billing\Charge;
use Illuminate\Support\Facades\DB;
use App\Models\AdminClub\BusinessAd;
use App\Models\AdminClub\PhysicalAd;
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
            $like   = $driver === 'pgsql' ? 'ilike' : 'like';

            // ── Anuncios digitales ──────────────────────────────────────────
            $query = BusinessAd::with(['status', 'member', 'category'])->where('club_id', $clubId);

            if ($search = $request->input("search")) {
                $query->where(function ($q) use ($search, $like) {
                    $q->where('name', $like, "%{$search}%")
                    ->orWhereHas('category', function($q) use ($search, $like){
                        $q->where('name', $like, "%{$search}%");
                    })
                    ->orWhereHas('member', function($u) use ($search, $like){
                        $u->where('first_name', $like, "%{$search}%")
                        ->orWhere('last_name', $like, "%{$search}%");
                    });
                });
            }

            $ads = $query->orderBy('id', 'desc')
                ->paginate($request->input("per_page", 10))
                ->withQueryString();

            // ── Anuncios físicos ────────────────────────────────────────────
            $physicalQuery = PhysicalAd::with('member')->where('club_id', $clubId);

            if ($physicalSearch = $request->input("physical_search")) {
                $physicalQuery->whereHas('member', function ($q) use ($physicalSearch, $like) {
                    $q->where('first_name', $like, "%{$physicalSearch}%")
                      ->orWhere('last_name', $like, "%{$physicalSearch}%");
                });
            }

            $physicalAds = $physicalQuery->orderBy('id', 'desc')
                ->paginate(
                    $request->input("physical_per_page", 10),
                    ['*'],
                    'physical_page'
                )
                ->withQueryString();

            return Inertia::render('AdminClubs/BusinessAds/Index', [
                'ads'         => $ads,
                'physicalAds' => $physicalAds,
            ]);
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('AdminClubs/BusinessAds/Index', [
                'ads'         => ['data' => [], 'total' => 0],
                'physicalAds' => ['data' => [], 'total' => 0],
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
