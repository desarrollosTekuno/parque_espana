<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
use App\Models\AdminClub\ClubParkFile;
use App\Models\AdminClub\ParkFile;
use App\Models\Files\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:files.index')->only('index');
        $this->middleware('permission:files.store')->only('store');
        // $this->middleware('permission:files.update')->only(['update', 'toggleClub']);
        $this->middleware('permission:files.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));
            $driver = DB::getDriverName();
            $prefix = 'files';

            $query = File::query()
                ->with([
                    'clubFile' => fn ($q) => $q->where('club_id', $clubId),
                ]);

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('code', $like, "%{$search}%")
                        ->orWhere('name', $like, "%{$search}%")
                        ->orWhere('description', $like, "%{$search}%");
                });
            }

            if (($activeOnly = $request->input("{$prefix}_is_active")) !== null && $activeOnly !== '') {
                $query->where('is_active', filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN));
            }

            $files = $query
                ->orderBy('id', 'desc')
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (ParkFile $file) {
                    $clubFile = $file->clubParkFiles->first();

                    return [
                        'id'                  => $file->id,
                        'name'                => $file->name,
                        'description'         => $file->description,
                        'file_original_name'  => $file->file_original_name,
                        'file_mime_type'      => $file->file_mime_type,
                        'file_size_formatted' => $file->file_size_formatted,
                        'file_url'            => $file->file_url,
                        'is_active'           => $file->is_active,
                        'created_at'          => $file->created_at?->format('d/m/Y'),
                        'club_enabled'        => $clubFile !== null,
                        'club_display_order'  => $clubFile?->display_order ?? 0,
                    ];
                })
                ->appends($request->all());

            $currentClub = Club::query()
                ->select('id', 'name', 'code')
                ->find($clubId);

            return Inertia::render('AdminClubs/Files/Index', [
                'parkFiles'  => $files,
                'currentClub' => $currentClub ? [
                    'id'   => $currentClub->id,
                    'name' => $currentClub->name,
                    'code' => $currentClub->code,
                ] : null,
                'filters' => [
                    'search'    => $request->input("{$prefix}_search"),
                    'is_active' => $request->input("{$prefix}_is_active"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/Files/Index', [
                'parkFiles'    => ['data' => [], 'total' => 0],
                'currentClub'  => null,
                'filters'      => ['search' => null, 'is_active' => null],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'file'        => 'required|file|max:20480',
        ], [
            'name.required' => 'Debes ingresar un nombre.',
            'file.required' => 'Debes adjuntar un archivo.',
            'file.max'      => 'El archivo no debe pesar más de 20 MB.',
        ]);

        try {
            /** @var \Illuminate\Http\UploadedFile $uploadedFile */
            $uploadedFile = $request->file('file');
            $uploaded = $this->uploadFile($uploadedFile);

            ParkFile::create([
                'name'               => $request->name,
                'description'        => $request->description,
                'file_path'          => $uploaded['path'],
                'file_original_name' => $uploaded['original_name'],
                'file_mime_type'     => $uploaded['mime_type'],
                'file_size'          => $uploaded['size'],
            ]);

            return redirect()->back()->with('success', 'Archivo cargado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Error al cargar el archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, int|string $id)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'required|boolean',
            'file'        => 'nullable|file|max:20480',
        ], [
            'name.required' => 'Debes ingresar un nombre.',
            'file.max'      => 'El archivo no debe pesar más de 20 MB.',
        ]);

        try {
            $parkFile = ParkFile::findOrFail($id);

            if ($request->hasFile('file')) {
                $this->deleteFile($parkFile->file_path);
                /** @var \Illuminate\Http\UploadedFile $uploadedFile */
                $uploadedFile = $request->file('file');
                $uploaded = $this->uploadFile($uploadedFile);

                $parkFile->file_path          = $uploaded['path'];
                $parkFile->file_original_name = $uploaded['original_name'];
                $parkFile->file_mime_type     = $uploaded['mime_type'];
                $parkFile->file_size          = $uploaded['size'];
            }

            $parkFile->update([
                'name'               => $request->name,
                'description'        => $request->description,
                'is_active'          => $request->is_active,
                'file_path'          => $parkFile->file_path,
                'file_original_name' => $parkFile->file_original_name,
                'file_mime_type'     => $parkFile->file_mime_type,
                'file_size'          => $parkFile->file_size,
            ]);

            return redirect()->back()->with('success', 'Archivo actualizado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Error al actualizar el archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function destroy(int|string $id)
    {
        try {
            $parkFile = ParkFile::findOrFail($id);
            $this->deleteFile($parkFile->file_path);
            $parkFile->delete();

            return redirect()->back()->with('success', 'Archivo eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Error al eliminar el archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function toggleClub(Request $request, $id)
    {
        try {
            $clubId   = (int) ($request->club_id ?? session('club_id'));
            $parkFile = ParkFile::findOrFail($id);

            $existing = ClubParkFile::where('club_id', $clubId)
                ->where('park_file_id', $parkFile->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $message = 'Archivo deshabilitado para este club.';
            } else {
                $maxOrder = ClubParkFile::where('club_id', $clubId)->max('display_order') ?? 0;

                ClubParkFile::create([
                    'club_id'       => $clubId,
                    'park_file_id'  => $parkFile->id,
                    'is_active'     => true,
                    'display_order' => $maxOrder + 1,
                ]);
                $message = 'Archivo habilitado para este club.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al cambiar el estado del archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    private function uploadFile(\Illuminate\Http\UploadedFile $file): array
    {
        $directory = 'park-files';
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('spaces')->putFileAs($directory, $file, $filename, 'private');

        return [
            'path'          => "{$directory}/{$filename}",
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ];
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('spaces')->exists($path)) {
            Storage::disk('spaces')->delete($path);
        }
    }
}
