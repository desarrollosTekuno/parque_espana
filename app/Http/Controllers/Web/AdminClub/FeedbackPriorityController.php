<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Feedback\Priority;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FeedbackPriorityController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback-priorities.index')->only('index');
        $this->middleware('permission:feedback-priorities.store')->only('store');
        $this->middleware('permission:feedback-priorities.update')->only('update');
        $this->middleware('permission:feedback-priorities.destroy')->only('destroy');
    }

    public function index(Request $request) {
        try {
            $driver = DB::getDriverName();

            $query = Priority::query();

            if ($search = trim($request->input('search'))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                        ->orWhere('code', $operator, "%{$search}%");
                });
            }

            $priorities = $query
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 10))
                ->withQueryString();

            return Inertia::render('AdminClubs/FeedbackPriorities/Index', [
                'priorities' => $priorities,
            ]);

        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/FeedbackPriorities/Index', [
                'priorities' => [
                    'data' => [],
                    'total' => 0,
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request) {
        $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[A-Za-zÀ-ÿ0-9,\s]+$/u'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('feedback.priorities', 'code')],
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ], [
            'name.regex' => 'El nombre solo permite letras, numeros, comas y espacios.',
            'code.regex' => 'El codigo solo permite letras, numeros y guion bajo (_).',
        ]);

        try {
            Priority::create([
                'name' => $request->name,
                'code' => $request->code,
                'sort_order' => $request->sort_order,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return back()->with('success', 'Prioridad creada correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al crear la prioridad',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $id) {
        $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[A-Za-zÀ-ÿ0-9,\s]+$/u'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('feedback.priorities', 'code')->ignore($id)],
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ], [
            'name.regex' => 'El nombre solo permite letras, numeros, comas y espacios.',
            'code.regex' => 'El codigo solo permite letras, numeros y guion bajo (_).',
        ]);

        try {
            $priority = Priority::findOrFail($id);

            $priority->update([
                'name' => $request->name,
                'code' => $request->code,
                'sort_order' => $request->sort_order,
                'is_active' => $request->is_active,
            ]);

            return back()->with('success', 'Prioridad actualizada correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar la prioridad',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id) {
        try {
            $priority = Priority::findOrFail($id);
            $priority->delete();

            return back()->with('success', 'Prioridad eliminada correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar la prioridad',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
