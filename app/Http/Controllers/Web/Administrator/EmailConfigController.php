<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Http\Requests\EmailConfigRequest;
use App\Models\Administrator\Club;
use App\Models\Notifications\EmailConfig;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmailConfigController extends Controller {

    public function __construct() {
        $this->middleware('permission:email-configs.index')->only('index');
        $this->middleware('permission:email-configs.store')->only('store');
        $this->middleware('permission:email-configs.update')->only('update');
        $this->middleware('permission:email-configs.destroy')->only('destroy');
    }

    public function index(Request $request) {
        $emailConfigs = $this->getEmailConfigs($request);

        return Inertia::render('Administrator/EmailConfigs/Index', [
            'emailConfigs' => $emailConfigs,
            'clubs' => Club::query()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    private function getEmailConfigs(Request $request) {
        $driver = DB::getDriverName();
        $prefix = 'emailConfigs';

        $query = EmailConfig::query()->with(['club:id,name']);

        if ($search = $request->input("{$prefix}_search")) {
            $query->where(function ($q) use ($driver, $search) {
                $operator = $driver === 'pgsql' ? 'ilike' : 'like';

                $q->where('profile_name', $operator, "%{$search}%")
                    ->orWhere('host', $operator, "%{$search}%")
                    ->orWhere('from_address', $operator, "%{$search}%")
                    ->orWhere('from_name', $operator, "%{$search}%");
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $allowedSorts = ['id', 'profile_name', 'host', 'port', 'from_address', 'is_active', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $query->orderBy($sort, $order === 'asc' ? 'asc' : 'desc');

        return $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());
    }

    public function store(EmailConfigRequest $request) {
        try {
            $validated = $request->validated();

            if (! $request->filled('template_name')) {
                $validated['template_name'] = 'email_template';
            }

            EmailConfig::create($validated);

            return redirect()->back()->with('success', 'Configuracion de correo creada correctamente');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrio un error al crear la configuracion de correo',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(EmailConfigRequest $request, EmailConfig $emailConfig) {
        try {
            $validated = $request->validated();

            if (! $request->filled('template_name')) {
                $validated['template_name'] = 'email_template';
            }

            if (! $request->filled('password')) {
                unset($validated['password']);
            }

            $emailConfig->update($validated);

            return redirect()->back()->with('success', 'Configuracion de correo actualizada correctamente');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrio un error al actualizar la configuracion de correo',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(EmailConfig $emailConfig) {
        try {
            $emailConfig->delete();

            return redirect()->back()->with('success', 'Configuracion de correo eliminada correctamente');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrio un error al eliminar la configuracion de correo',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
