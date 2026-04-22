<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\AdminClub\BusinessCategory;

class BusinessCategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();

            $query = BusinessCategory::where('club_id', $clubId);

            if ($search = trim($request->input("search"))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%");
                });
            }

            $categories = $query
                ->orderBy('id', 'desc')
                ->paginate($request->input("per_page", 10))
                ->through(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'is_active' => $item->is_active,
                        'image' => $item->image ? Storage::url($item->image) : null,
                    ];
                })
                ->withQueryString();

            return Inertia::render('AdminClubs/BusinessCategories/Index', [
                'categories' => $categories
            ]);

        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/BusinessCategories/Index', [
                'categories' => [
                    'data' => [],
                    'total' => 0
                ],
                'messageError' => $e->getMessage()
            ]);
        }
    }

    public function store(Request $request)
    {
            $request->validate([
                'name' => 'required|string|max:255',
                'image' => 'nullable|image|max:2048'
            ],[
                'name.required' => 'Debes ingresar un nombre.',
                'image.max' => 'La imagen no debe pesar más de 2MB.'
            ]);

        try {

            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('business-categories', 'public');
            }

            BusinessCategory::create([
                'club_id' => session('club_id'),
                'name' => $request->name,
                'image' => $imagePath
            ]);

            return back()->with('success', 'Categoría creada correctamente');

        } catch (\Exception $e) {
            report($e);
            //dd($e->getMessage());
            return back()->withErrors([
                'messageError' => 'Error al crear la categoría',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function update(Request $request, $id)
    {
         $request->validate([
                'name' => 'required|string|max:255',
                'is_active' => 'required|boolean',
                'image' => 'nullable|image|max:2048'
            ],[
                'name.required' => 'Debes ingresar un nombre.',
                'image.max' => 'La imagen no debe pesar más de 2MB.'
            ]);

        try {
            $category = BusinessCategory::findOrFail($id);

            if ($request->hasFile('image')) {
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }

                $category->image = $request->file('image')->store('business-categories', 'public');
            }

            $category->update([
                'name' => $request->name,
                'is_active' => $request->is_active
            ]);

            return back()->with('success', 'Categoría actualizada');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar la categoría',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function destroy($id)
    {
        try {
            $category = BusinessCategory::findOrFail($id);

            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $category->delete();

            return back()->with('success', 'Categoría eliminada');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar la categoría',
                'exception' => $e->getMessage()
            ]);
        }
    }
}

?>