<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Feedback\Status;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FeedbackStatusController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback-statuses.index')->only('index');
        $this->middleware('permission:feedback-statuses.store')->only('store');
        $this->middleware('permission:feedback-statuses.update')->only('update');
        $this->middleware('permission:feedback-statuses.destroy')->only('destroy');
    }

    public function index(Request $request) {
        try {
            $driver = DB::getDriverName();

            $query = Status::query();

            if ($search = trim($request->input('search'))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                        ->orWhere('code', $operator, "%{$search}%")
                        ->orWhere('color', $operator, "%{$search}%");
                });
            }

            $statuses = $query
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 10))
                ->withQueryString();

            return Inertia::render('AdminClubs/FeedbackStatuses/Index', [
                'statuses' => $statuses,
            ]);

        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/FeedbackStatuses/Index', [
                'statuses' => [
                    'data' => [],
                    'total' => 0,
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request) {
        $request->validate([
            'name' => ['required', 'string', 'max:35', 'regex:/^[A-Za-zÀ-ÿ0-9,\s]+$/u'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('feedback.statuses', 'code')],
            'color' => ['nullable', 'string', 'max:20', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ], [
            'name.regex' => 'El nombre solo permite letras, numeros, comas y espacios.',
            'code.regex' => 'El codigo solo permite letras, numeros y guion bajo (_).',
            'color.regex' => 'El color debe estar en formato hexadecimal valido.',
        ]);

        try {
            Status::create([
                'name' => $request->name,
                'code' => $request->code,
                'color' => $request->color,
                'sort_order' => $request->sort_order,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return back()->with('success', 'Estatus creado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al crear el estatus',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $id) {
        $request->validate([
            'name' => ['required', 'string', 'max:35', 'regex:/^[A-Za-zÀ-ÿ0-9,\s]+$/u'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('feedback.statuses', 'code')->ignore($id)],
            'color' => ['nullable', 'string', 'max:20', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ], [
            'name.regex' => 'El nombre solo permite letras, numeros, comas y espacios.',
            'code.regex' => 'El codigo solo permite letras, numeros y guion bajo (_).',
            'color.regex' => 'El color debe estar en formato hexadecimal valido.',
        ]);

        try {
            $status = Status::findOrFail($id);

            $status->update([
                'name' => $request->name,
                'code' => $request->code,
                'color' => $request->color,
                'sort_order' => $request->sort_order,
                'is_active' => $request->is_active,
            ]);

            return back()->with('success', 'Estatus actualizado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar el estatus',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id) {
        try {
            $status = Status::findOrFail($id);
            $status->delete();

            return back()->with('success', 'Estatus eliminado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar el estatus',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
