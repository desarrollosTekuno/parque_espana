<?php

namespace App\Http\Controllers\Web\AdminClub\Feedback;

use Illuminate\Routing\Controller;
use App\Models\Feedback\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FeedbackCategoryController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback-categories.index')->only('index');
        $this->middleware('permission:feedback-categories.store')->only('store');
        $this->middleware('permission:feedback-categories.update')->only('update');
        $this->middleware('permission:feedback-categories.destroy')->only('destroy');
    }

    public function index(Request $request) {
        try {
            $driver = DB::getDriverName();

            $query = Category::query();

            if ($search = trim($request->input('search'))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                        ->orWhere('code', $operator, "%{$search}%")
                        ->orWhere('description', $operator, "%{$search}%");
                });
            }

            $categories = $query
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 10))
                ->withQueryString();

            return Inertia::render('AdminClubs/FeedbackCategories/Index', [
                'categories' => $categories,
            ]);

        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/FeedbackCategories/Index', [
                'categories' => [
                    'data' => [],
                    'total' => 0,
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:120',
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-zÀ-ÿ0-9,\s]+$/u', Rule::unique('feedback.categories', 'code')],
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ], [
            'code.regex' => 'El codigo solo permite letras, numeros, comas y espacios.',
        ]);

        try {
            Category::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return back()->with('success', 'Categoría creada correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al crear la categoría',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $id) {
        $request->validate([
            'name' => 'required|string|max:120',
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-zÀ-ÿ0-9,\s]+$/u', Rule::unique('feedback.categories', 'code')->ignore($id)],
            'description' => 'nullable|string|max:500',
            'is_active' => 'required|boolean',
        ], [
            'code.regex' => 'El codigo solo permite letras, numeros, comas y espacios.',
        ]);

        try {
            $category = Category::findOrFail($id);

            $category->update([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->is_active,
            ]);

            return back()->with('success', 'Categoría actualizada correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar la categoría',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id) {
        try {
            $category = Category::findOrFail($id);
            $category->delete();

            return back()->with('success', 'Categoría eliminada correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar la categoría',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
