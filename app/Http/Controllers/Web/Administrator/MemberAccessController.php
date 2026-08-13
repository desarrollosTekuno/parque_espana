<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Members\Member;
use App\Models\User;
use App\Services\MemberAccessService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class MemberAccessController extends Controller
{
    public function __construct(private MemberAccessService $accessService)
    {
        $this->middleware('permission:member-access.index')->only('index');
        $this->middleware('permission:member-access.store')->only('store');
        $this->middleware('permission:member-access.destroy')->only('destroy');
    }

    /**
     * Lista todos los miembros con su estado de acceso a la app móvil.
     */
    public function index(Request $request)
    {
        $prefix = 'members_access';
        $driver = DB::getDriverName();

        $query = Member::with(['user:id,name,email', 'accountMemberships.membershipAccount.club'])
            ->orderBy('last_name');

        if ($search = $request->input("{$prefix}_search")) {
            $query->where(function ($q) use ($search, $driver) {
                $op = $driver === 'pgsql' ? 'ilike' : 'like';
                $q->where('first_name',        $op, "%{$search}%")
                  ->orWhere('last_name',        $op, "%{$search}%")
                  ->orWhere('second_last_name', $op, "%{$search}%")
                  ->orWhere('email',            $op, "%{$search}%");
            });
        }

        if ($request->input("{$prefix}_access") === 'with') {
            $query->whereNotNull('user_id');
        } elseif ($request->input("{$prefix}_access") === 'without') {
            $query->whereNull('user_id');
        }

        $members = $query->paginate(
            $request->input("{$prefix}_per_page", 15),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('Administrator/MemberAccess/Index', [
            'members_access' => $members,
        ]);
    }

    /**
     * Crea un usuario para el miembro, lo vincula y asigna los roles
     * correspondientes según los clubs en los que tiene membresía activa.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => ['required', 'integer'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors(
                array_merge(
                    ['messageError' => $validator->errors()->first(), 'exception' => ''],
                    $validator->errors()->toArray()
                )
            );
        }

        DB::beginTransaction();
        try {
            $member = Member::findOrFail($request->member_id);

            if ($member->user_id) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Este miembro ya tiene acceso a la app.',
                    'exception'    => '',
                ]);
            }

            if (!$member->birthdate || \Carbon\Carbon::parse($member->birthdate)->age < 14) {
                return redirect()->back()->withErrors([
                    'messageError' => 'El acceso a la app móvil solo puede otorgarse a personas mayores de 14 años.',
                    'exception'    => '',
                ]);
            }

            $user = User::create([
                'name'     => $member->full_name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $member->update(['user_id' => $user->id, 'email' => $request->email]);

            $this->accessService->syncMobileRoles($member->fresh());

            DB::commit();
            return redirect()->back()->with('success', 'Acceso a la app móvil otorgado con éxito.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al otorgar el acceso.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Revoca el acceso del miembro eliminando su usuario vinculado.
     */
    public function destroy(Member $member)
    {
        DB::beginTransaction();
        try {
            if (!$member->user_id) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Este miembro no tiene acceso activo.',
                    'exception'    => '',
                ]);
            }

            $user = $member->user;

            $member->update(['user_id' => null, 'email' => null]);

            $user->tokens()->delete();
            $user->roles()->detach();
            $user->clubs()->detach();
            $user->delete();

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            DB::commit();
            return redirect()->back()->with('success', 'Acceso a la app móvil revocado con éxito.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al revocar el acceso.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }
}
