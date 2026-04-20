<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Context;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Role;
use App\Models\User;
use App\Rules\ExistsInSchema;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class MemberAccessController extends Controller
{
    /**
     * Mapeo de código de club al valor de contexto móvil correspondiente.
     * Si se agregan más clubs en el futuro, extender este mapa.
     */
    private const CLUB_CONTEXT_MAP = [
        'PE1' => 'mobile_club_1',
        'PE2' => 'mobile_club_2',
    ];

    public function __construct()
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
                $q->where('first_name',       $op, "%{$search}%")
                  ->orWhere('last_name',       $op, "%{$search}%")
                  ->orWhere('second_last_name',$op, "%{$search}%")
                  ->orWhere('email',           $op, "%{$search}%");
            });
        }

        // Filtro: con acceso / sin acceso
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
            'member_id' => ['required',  new ExistsInSchema('members', 'members', 'id')],
            // 'source_membership_id' => ['nullable', new ExistsInSchema('memberships', 'memberships', 'id')],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors(
                array_merge(
                    ['messageError' => $validator->errors()->first(), 'exception' => ''],
                    $validator->errors()->toArray()   // expone email, password, etc. como claves individuales
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

            // Crear el usuario
            $user = User::create([
                'name'     => $member->full_name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Vincular al miembro
            $member->update(['user_id' => $user->id]);

            // Asignar roles según los clubs con membresía activa
            $this->assignMobileRoles($user, $member);

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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

            // Desvincular primero
            $member->update(['user_id' => null]);

            // Eliminar tokens y usuario
            $user->tokens()->delete();
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

    /**
     * Determina los clubs activos del miembro y le asigna el rol
     * correcto (socio_titular o socio_dependiente) por cada uno.
     */
    private function assignMobileRoles(User $user, Member $member): void
    {
        $accountMemberships = MembershipAccountMember::with('membershipAccount.club')
            ->where('member_id', $member->id)
            ->get();

        foreach ($accountMemberships as $accountMember) {
            $club = $accountMember->membershipAccount?->club;

            if (!$club) {
                continue;
            }

            $contextValue = self::CLUB_CONTEXT_MAP[$club->code] ?? null;

            if (!$contextValue) {
                continue;
            }

            $context = Context::where('value', $contextValue)->first();

            if (!$context) {
                continue;
            }

            $roleName = $accountMember->is_primary_holder
                ? 'socio_titular'
                : 'socio_dependiente';

            $role = Role::where('name', $roleName)
                ->where('context_id', $context->id)
                ->first();

            if ($role) {
                $user->assignRole($role);

                // Agregar el club a user_clubs si no existe
                if (!$user->clubs()->where('club_id', $club->id)->exists()) {
                    $user->clubs()->attach($club->id);
                }
            }
        }
    }
}
