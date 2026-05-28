<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
use App\Models\Files\ClubFile;
use App\Models\Files\File;
use App\Rules\ExistsInSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:files.index')->only('index');
        $this->middleware('permission:files.store')->only('store');
        $this->middleware('permission:files.update')->only('update');
        $this->middleware('permission:files.destroy')->only('destroy');
        $this->middleware('permission:files.club-file.upload')->only('uploadClubFile');
        $this->middleware('permission:files.club-file.destroy')->only('destroyClubFile');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));
            $driver = DB::getDriverName();
            $prefix = 'files';

            $query = File::query()
                ->with([
                    'clubFiles' => fn ($q) => $q->where('club_id', $clubId),
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
                ->orderBy('id', 'asc')
                ->paginate(
                    $request->input("{$prefix}_per_page", 25),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (File $file) {
                    $clubFile = $file->clubFiles->first();

                    return [
                        'id'                 => $file->id,
                        'code'               => $file->code,
                        'name'               => $file->name,
                        'description'        => $file->description,
                        'is_required'        => $file->is_required,
                        'is_active'          => $file->is_active,
                        'allowed_mime_types' => $file->allowed_mime_types,
                        'max_size_bytes'     => $file->max_size_bytes,
                        'club_file'          => $clubFile ? [
                            'id'                  => $clubFile->id,
                            'file_original_name'  => $clubFile->file_original_name,
                            'file_mime_type'      => $clubFile->file_mime_type,
                            'file_size_formatted' => $clubFile->file_size_formatted,
                            'file_url'            => $clubFile->file_url,
                            'is_active'           => $clubFile->is_active,
                            'display_order'       => $clubFile->display_order,
                        ] : null,
                    ];
                })
                ->appends($request->all());

            $currentClub = Club::query()
                ->select('id', 'name', 'code')
                ->find($clubId);

            return Inertia::render('AdminClubs/Files/Index', [
                'files'       => $files,
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
            return Inertia::render('AdminClubs/Files/Index', [
                'files'        => ['data' => [], 'total' => 0],
                'currentClub'  => null,
                'filters'      => ['search' => null, 'is_active' => null],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'code'                 => ['required', 'string', 'max:50', Rule::unique(File::class, 'code')],
                'name'                 => 'required|string|max:255',
                'description'          => 'nullable|string|max:500',
                'is_required'          => 'required|boolean',
                'is_active'            => 'required|boolean',
                'allowed_mime_types'   => 'nullable|array',
                'allowed_mime_types.*' => 'string',
                'max_size_mb'          => 'nullable|numeric|min:0.1|max:100',
            ], [
                'code.required' => 'Debes ingresar un código.',
                'code.string' => 'El código debe ser una cadena de texto.',
                'code.max' => 'El código debe tener menos de 50 caracteres.',
                'code.unique' => 'El código ya está en uso.',

                'name.required' => 'Debes ingresar un nombre.',
                'name.string' => 'El nombre debe ser una cadena de texto.',
                'name.max' => 'El nombre debe tener menos de 255 caracteres.',

                'description.string' => 'La descripción debe ser una cadena de texto.',
                'description.max' => 'La descripción debe tener menos de 500 caracteres.',

                'is_required.required' => 'Debes ingresar si el archivo es requerido.',
                'is_required.boolean' => 'El estado debe ser verdadero o falso.',

                'is_active.required' => 'Debes ingresar si el archivo es activo.',
                'is_active.boolean' => 'El estado debe ser verdadero o falso.',

                'allowed_mime_types.array' => 'Los tipos MIME permitidos deben ser un arreglo.',
                'allowed_mime_types.*.string' => 'Cada tipo MIME permitido debe ser una cadena de texto.',

                'max_size_mb.numeric' => 'El tamaño máximo debe ser un número.',
                'max_size_mb.min' => 'El tamaño minimo debe ser mayor a 0.1.',
                'max_size_mb.max' => 'El tamaño maximo debe ser menor a 100.',
            ]);

            File::create([
                'code'               => $validated['code'],
                'name'               => $validated['name'],
                'description'        => $validated['description'],
                'is_required'        => $validated['is_required'],
                'is_active'          => $validated['is_active'],
                'allowed_mime_types' => $validated['allowed_mime_types'],
                'max_size_bytes'     => $validated['max_size_mb'] ? (int) ($request->max_size_mb * 1024 * 1024) : (2 * 1024 * 1024), // Default 2 MB
            ]);

            return redirect()->back()->with('success', 'Formato de archivo creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Error al crear el formato de archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, File $file)
    {
        try {

            $validated = $request->validate([
                'code'                 => ['required', 'string', 'max:50', Rule::unique(File::class, 'code')->ignore($file->id)],
                'name'                 => 'required|string|max:255',
                'description'          => 'nullable|string|max:500',
                'is_required'          => 'required|boolean',
                'is_active'            => 'required|boolean',
                'allowed_mime_types'   => 'nullable|array',
                'allowed_mime_types.*' => 'string',
                'max_size_mb'          => 'nullable|numeric|min:0.1|max:100',
            ], [
                'code.required' => 'Debes ingresar un código.',
                'code.string' => 'El código debe ser una cadena de texto.',
                'code.max' => 'El código debe tener menos de 50 caracteres.',
                'code.unique' => 'El código ya está en uso.',

                'name.required' => 'Debes ingresar un nombre.',
                'name.string' => 'El nombre debe ser una cadena de texto.',
                'name.max' => 'El nombre debe tener menos de 255 caracteres.',

                'description.string' => 'La descripción debe ser una cadena de texto.',
                'description.max' => 'La descripción debe tener menos de 500 caracteres.',

                'is_required.required' => 'Debes ingresar si el archivo es requerido.',
                'is_required.boolean' => 'El estado debe ser verdadero o falso.',

                'is_active.required' => 'Debes ingresar si el archivo es activo.',
                'is_active.boolean' => 'El estado debe ser verdadero o falso.',

                'allowed_mime_types.array' => 'Los tipos MIME permitidos deben ser un arreglo.',
                'allowed_mime_types.*.string' => 'Cada tipo MIME permitido debe ser una cadena de texto.',

                'max_size_mb.numeric' => 'El tamaño máximo debe ser un número.',
                'max_size_mb.min' => 'El tamaño minimo debe ser mayor a 0.1.',
                'max_size_mb.max' => 'El tamaño maximo debe ser menor a 100.',
            ]);


            $file->update([
                'code'               => $validated['code'],
                'name'               => $validated['name'],
                'description'        => $validated['description'],
                'is_required'        => $validated['is_required'],
                'is_active'          => $validated['is_active'],
                'allowed_mime_types' => $validated['allowed_mime_types'],
                'max_size_bytes'     => $validated['max_size_mb'] ? (int) ($validated['max_size_mb'] * 1024 * 1024) : (2 * 1024 * 1024), // Default 2 MB
            ]);

            return redirect()->back()->with('success', 'Formato de archivo actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Error al actualizar el formato de archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function destroy(File $file)
    {
        try {
            $file->delete();

            return redirect()->back()->with('success', 'Formato de archivo eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Error al eliminar el formato de archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function uploadClubFile(Request $request, File $file)
    {
        try {

            $clubId = (int) ($request->club_id ?? session('club_id'));

            $maxKb = (int) ceil($file->max_size_bytes / 1024);

            $rules = ['file' => "required|file|max:{$maxKb}"];
            if (!empty($file->allowed_mime_types)) {
                $rules['file'] .= '|mimetypes:' . implode(',', $file->allowed_mime_types);
            }

            $request->validate($rules, [
                'file.required'  => 'Debes adjuntar un archivo.',
                'file.max'       => 'El archivo supera el tamaño máximo permitido.',
                'file.mimetypes' => 'El tipo de archivo no está permitido para este formato.',
            ]);

            $existing = ClubFile::where('club_id', $clubId)
                ->where('file_id', $file->id)
                ->first();

            if ($existing) {
                $this->deleteFile($existing->file_path);
            }

            $uploadedFile = $request->file('file');
            $uploaded     = $this->uploadFile($uploadedFile);

            $displayOrder = $existing ? $existing->display_order : (ClubFile::where('club_id', $clubId)->max('display_order') ?? 0) + 1;

            $data = [
                'club_id'            => $clubId,
                'file_id'            => $file->id,
                'file_path'          => $uploaded['path'],
                'file_original_name' => $uploaded['original_name'],
                'file_mime_type'     => $uploaded['mime_type'],
                'file_size'          => $uploaded['size'],
                'is_active'          => true,
                'display_order'      => $displayOrder,
            ];

            if ($existing) {
                $existing->update($data);
            } else {
                ClubFile::create($data);
            }

            return redirect()->back()->with('success', 'Archivo cargado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Error al cargar el archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function destroyClubFile(Request $request, File $file)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));

            $clubFile = ClubFile::where('club_id', $clubId)
                ->where('file_id', $file->id)
                ->firstOrFail();

            $clubFile->delete();

            return redirect()->back()->with('success', 'Archivo eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Error al eliminar el archivo.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    private function uploadFile(UploadedFile $file): array
    {
        $directory = 'club-files';
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
