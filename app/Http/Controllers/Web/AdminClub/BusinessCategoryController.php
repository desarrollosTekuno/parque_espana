<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\AdminClub\BusinessCategory;

class BusinessCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:business-categories.index')->only('index');
        $this->middleware('permission:business-categories.store')->only('store');
        $this->middleware('permission:business-categories.update')->only('update');
        $this->middleware('permission:business-categories.delete')->only('delete');
    }

    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();

            $query = BusinessCategory::where('club_id', $clubId);

            if ($search = trim($request->input('search'))) {
                $operator = $driver === 'pgsql' ? 'ilike' : 'like';
                $query->where('name', $operator, "%{$search}%");
            }

            $categories = $query
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 10))
                ->through(fn ($item) => [
                    'id'        => $item->id,
                    'name'      => $item->name,
                    'is_active' => $item->is_active,
                    'image'     => $item->image_url,
                ])
                ->withQueryString();

            return Inertia::render('AdminClubs/BusinessCategories/Index', [
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('AdminClubs/BusinessCategories/Index', [
                'categories'   => ['data' => [], 'total' => 0],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
        ], [
            'name.required' => 'Debes ingresar un nombre.',
            'image.max'     => 'La imagen no debe pesar más de 2MB.',
        ]);

        try {
            $clubId    = session('club_id');
            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $this->uploadImage($request->file('image'), $clubId);
            }

            BusinessCategory::create([
                'club_id' => $clubId,
                'name'    => $request->name,
                'image'   => $imagePath,
            ]);

            return back()->with('success', 'Categoría creada correctamente');
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'messageError' => 'Error al crear la categoría',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'is_active' => 'required|boolean',
            'image'     => 'nullable|image|max:2048',
        ], [
            'name.required' => 'Debes ingresar un nombre.',
            'image.max'     => 'La imagen no debe pesar más de 2MB.',
        ]);

        try {
            $clubId   = session('club_id');
            $category = BusinessCategory::findOrFail($id);

            if ($request->has('remove_image')) {
                $this->deleteImage($category->image);
                $category->image = null;
            }

            if ($request->hasFile('image')) {
                $this->deleteImage($category->image);
                $category->image = $this->uploadImage($request->file('image'), $clubId);
            }

            $category->update([
                'name'      => $request->name,
                'is_active' => $request->is_active,
                'image'     => $category->image,
            ]);

            return back()->with('success', 'Categoría actualizada');
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'messageError' => 'Error al actualizar la categoría',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id)
    {
        try {
            $category = BusinessCategory::findOrFail($id);
            $this->deleteImage($category->image);
            $category->delete();

            return back()->with('success', 'Categoría eliminada');
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'messageError' => 'Error al eliminar la categoría',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    private function uploadImage(\Illuminate\Http\UploadedFile $file, int|string $clubId): string
    {
        $clubCode  = \App\Models\Administrator\Club::find($clubId)?->code ?? $clubId;
        $directory = "clubs/{$clubCode}/business-categories";
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('spaces')->putFileAs($directory, $file, $filename, 'public');

        return "{$directory}/{$filename}";
    }

    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('spaces')->exists($path)) {
            Storage::disk('spaces')->delete($path);
        }
    }
}
