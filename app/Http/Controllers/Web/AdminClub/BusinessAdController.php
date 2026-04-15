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
        $this->middleware('permission:business-ads.confirm-payment')->only('confirmPayment');
        $this->middleware('permission:business-ads.publish')->only('publish');
        $this->middleware('permission:business-ads.destroy')->only('destroy');
    }

    public function index(Request $request){
        try {
            $driver = DB::getDriverName();
            $query = BusinessAd::with(['status', 'member']);
           // dd($query);
            if ($search = $request->input("search")) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('title', $operator, "%{$search}%")
                    ->orWhere('description', $operator, "%{$search}%")
                    ->orWhereHas('user', function($u) use ($search, $operator){
                        $u->where('name', $operator, "%{$search}%");
                    });
                });
            }
            $query->orderBy('id', 'desc');
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
                throw new \Exception('El socio no tiene cuenta de membresía');
            }
            $membership = $accountMembership->membershipAccount->memberships->first();
            if (!$membership) {
                throw new \Exception('No se encontró membership');
            }
            $concept = ChargeConcept::findOrFail(3); 
            Charge::create([
                'membership_account_id' => $accountMembership->membership_account_id,
                'membership_id' => $membership->id ?? null,
                'member_id' => $ad->member_id,
                'concept_id' => 3, 
                'description' => $concept->description,
                'amount' => $concept->default_amount, 
                'balance' => $concept->default_amount,
                'issue_date' => now(),
                'due_date' => now()->addDays(7),
                'period_year' => now()->year,
                'period_month' => now()->month,
                'allows_partial_payments' => false,
                'status' => 'pending',
                'metadata' => [
                    'business_ad_id' => $ad->id
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
    public function reject($id){
        try {
            DB::beginTransaction();

            $ad = BusinessAd::findOrFail($id);
            $ad->update([
                'status_id' => 2 // rejected
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

    /*     Confirmar pago    */
    public function confirmPayment($id){
        try {
            DB::beginTransaction();
            $ad = BusinessAd::findOrFail($id);

            if ($ad->status_id != 3) {
                throw ValidationException::withMessages([
                    'status' => 'El anuncio debe estar aprobado antes de confirmar el pago'
                ]);
            }

            $ad->update([
                'status_id' => 4, // paid
                'paid_at' => now()
            ]);

            DB::commit();

            return back()->with('success','Pago confirmado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }

    /*      Publicar anuncio    */
    public function publish($id){
        try {
            DB::beginTransaction();

            $ad = BusinessAd::findOrFail($id);

            if ($ad->status_id != 4) {
                throw ValidationException::withMessages([
                    'status' => 'El anuncio debe estar pagado antes de publicarse'
                ]);
            }

            $ad->update([
                'status_id' => 5, // published
                'published_at' => now(),
                'expires_at' => now()->addMonth()
            ]);

            DB::commit();

            return back()->with('success','Anuncio publicado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }

    /*     Eliminar    */
    public function destroy(BusinessAd $businessAd){
        try {

            $businessAd->delete();
            return back()->with('success','Anuncio eliminado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }
}
