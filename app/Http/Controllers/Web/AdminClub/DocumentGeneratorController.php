<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
use App\Models\Files\ClubFile;
use App\Models\Files\ClubFileCounter;
use App\Models\Files\File;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Str;

class DocumentGeneratorController extends Controller {

    public function __construct()
    {
        $this->middleware('permission:file-generator.index')->only('index');
        $this->middleware('permission:file-generator.download')->only('download');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));
            $prefix = 'documents';

            $query = File::query()
                ->where('is_active', true)
                ->whereHas('clubFiles', function ($q) use ($clubId) {
                    $q->where('club_id', $clubId)
                      ->where('is_active', true)
                      ->whereNotNull('file_path');
                });

            if ($search = $request->input("{$prefix}_search")) {
                $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($builder) use ($search, $like) {
                    $builder->where('code', $like, "%{$search}%")
                        ->orWhere('name', $like, "%{$search}%");
                });
            }

            $documents = $query
                ->orderBy('name')
                ->paginate(
                    $request->input("{$prefix}_per_page", 25),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(fn (File $file) => [
                    'id'             => $file->id,
                    'code'           => $file->code,
                    'name'           => $file->name,
                    'description'    => $file->description,
                    'module'         => $file->module,
                    'requires_input' => $this->requiresUserInput($file->code),
                ])
                ->appends($request->all());

            $currentClub = Club::query()->select('id', 'name', 'code')->find($clubId);

            return Inertia::render('AdminClubs/FileGenerator/Index', [
                'documents'   => $documents,
                'currentClub' => $currentClub,
                'filters'     => [
                    'search' => $request->input("{$prefix}_search"),
                ],
            ]);
        } catch (\Exception $e) {
            return Inertia::render('AdminClubs/FileGenerator/Index', [
                'documents'    => ['data' => [], 'total' => 0],
                'currentClub'  => null,
                'filters'      => ['search' => null],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function download(Request $request, File $file)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));

            $clubFile = ClubFile::where('club_id', $clubId)
                ->where('file_id', $file->id)
                ->where('is_active', true)
                ->firstOrFail();

            $templateBytes = Storage::disk('spaces')->get($clubFile->file_path);
            $tempTemplate  = tempnam(sys_get_temp_dir(), 'tpl_') . '.docx';
            file_put_contents($tempTemplate, $templateBytes);

            \Log::info('=== INICIO GENERACIÓN DOCUMENTO ===', [
                'file_code'         => $file->code,
                'file_name'         => $file->name,
                'club_id'           => $clubId,
                'template_path'     => $tempTemplate,
                'template_size'     => filesize($tempTemplate),
                'template_exists'   => file_exists($tempTemplate),
            ]);

            $variables = $this->buildVariables($file, $clubId, $request);
            \Log::info('Variables construidas', $variables);

            $processor = new \PhpOffice\PhpWord\TemplateProcessor($tempTemplate);

            // ESTE ES EL LOG CLAVE
            \Log::info('Variables que PhpWord detecta en la plantilla', [
                'detectadas' => $processor->getVariables(),
            ]);

            foreach ($variables as $key => $value) {
                $processor->setValue($key, $value);
                \Log::info("Reemplazando ${key}", ['valor' => $value]);
            }

            $outputPath = tempnam(sys_get_temp_dir(), 'out_') . '.docx';
            $processor->saveAs($outputPath);

            \Log::info('Archivo generado', [
                'output_path' => $outputPath,
                'output_size' => filesize($outputPath),
            ]);

            @unlink($tempTemplate);

            $downloadName = \Str::slug($file->name) . '-' . now()->format('Ymd-His') . '.docx';

            return response()->download($outputPath, $downloadName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Error generando documento', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea'   => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors([
                'messageError' => 'Error al generar el documento.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Indica si el formato requiere que el usuario proporcione datos antes de generar.
     */
    private function requiresUserInput(string $code): bool
    {
        return match ($code) {
            'SOL_USER_CL' => false,
            'SOL_USER_SL' => false,
            'SOL_PERMISSION' => false,
            'SOL_LOCKER' => true,
            default     => false,
        };
    }

    /**
     * Construye el arreglo de variables a reemplazar en la plantilla.
     * Aquí vive la lógica específica por formato.
     */
    private function buildVariables(File $file, int $clubId, Request $request): array
    {
        return match ($file->code) {

            // Casillero
            'SOL_LOCKER' => [
                'folio'  => ClubFileCounter::nextFolio($clubId, $file->id),
                'gender' => $request->validate([
                    'gender' => 'required|in:DAMA,CABALLERO,NIÑO',
                ])['gender'],
                'year'   => now()->translatedFormat('Y'),
            ],

            // Constancia: solo fecha actual
            'SOL_PERMISSION' => [
                'day' =>  now()->translatedFormat('d'),
                'month' => now()->locale('es')->translatedFormat('F'),
                'year' => now()->translatedFormat('Y'),
            ],

            // Estos dos formatos comparten la misma lógica: solo folio
            'SOL_USER_CL', 'SOL_USER_SL' => [
                'folio' => ClubFileCounter::nextFolio($clubId, $file->id),
            ],

            default => throw new \Exception("El formato '{$file->code}' no tiene lógica de generación configurada."),
        };
    }
}
