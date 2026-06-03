<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClubController extends Controller
{

    public function index(Request $request)
    {
        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'clubs';

        // Query base
        $query = Club::query();

        // Filtro de búsqueda
        if ($search = $request->input("{$prefix}_search")) {
            $query->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('address', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
        }

        // Ordenamiento
        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');
        $query->orderBy($sort, $order);

        // Paginación
        $clubs = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page" // nombre del query param para la página
        )->appends($request->all()); // mantener todos los query params

        return Inertia::render('Administrator/Clubs/Index', [
            // return Inertia::render('Template', [
            'clubs' => $clubs,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:20',
            'address' => 'required|string',
            'logo'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        try {
            $data = $request->except('logo');

            if ($request->hasFile('logo')) {
                $data['logo_path'] = $this->uploadLogo(
                    $request->file('logo'),
                    $request->input('code') ?? 'club'
                );
            }

            Club::create($data);

            return redirect()->back()->with('success', 'Club creado con éxito!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el club',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, Club $club)
    {
        $request->validate([
            'name'    => 'required|string|max:20',
            'address' => 'required|string',
            'logo'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        try {
            $data = $request->except('logo');

            if ($request->hasFile('logo')) {
                $this->deleteLogo($club->logo_path);
                $data['logo_path'] = $this->uploadLogo(
                    $request->file('logo'),
                    $club->code ?? "club-{$club->id}"
                );
            }

            $club->update($data);

            return redirect()->back()->with('success', 'Club actualizado con éxito!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el club',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Club $club)
    {
        try {
            // validar que el club no tenga dependencias antes de eliminarlo
            if ($club->userClub()->count() > 0) {
                return redirect()->back()->withErrors([
                    'messageError' => 'No se puede eliminar el club porque tiene usuarios asociados',
                    'exception'=> ''
                ]);
            }
            if ($club->amenities()->count() > 0) {
                return redirect()->back()->withErrors([
                    'messageError' => 'No se puede eliminar el club porque tiene amenidades asociadas',
                    'exception'=> ''
                ]);
            }
            $club->delete();
            return redirect()->back()->with('success', 'Club eliminado con éxito!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar el club',
                'exception' => $e->getMessage(),
            ]);
        }
        
    }

    public function changeClub(Request $request)
    {
        session(['club_id' => $request->club_id]);

        $referer  = $request->headers->get('referer', '/');
        $cleanUrl = strtok($referer, '?');

        return redirect($cleanUrl);
    }

    // ─── Helpers de logo ───────────────────────────────────────────────────────

    private function uploadLogo(\Illuminate\Http\UploadedFile $file, string $clubCode): string
    {
        $directory = "clubs/{$clubCode}/logo";
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('spaces')->putFileAs($directory, $file, $filename, 'public');

        return "{$directory}/{$filename}";
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && Storage::disk('spaces')->exists($path)) {
            Storage::disk('spaces')->delete($path);
        }
    }
}
