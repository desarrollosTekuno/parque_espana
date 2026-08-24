<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
use App\Models\Files\ClubFile;
use App\Models\Files\ClubFileCounter;
use App\Models\Files\File;
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
        $this->middleware('permission:documents.index')->only('index');
        $this->middleware('permission:documents.download')->only('download');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));

            // Solo formatos activos que tienen plantilla cargada para este club
            $documents = File::query()
                ->where('is_active', true)
                ->whereHas('clubFiles', function ($q) use ($clubId) {
                    $q->where('club_id', $clubId)
                      ->where('is_active', true)
                      ->whereNotNull('file_path');
                })
                ->orderBy('name')
                ->get()
                ->map(function (File $file) {
                    return [
                        'id'          => $file->id,
                        'code'        => $file->code,
                        'name'        => $file->name,
                        'description' => $file->description,
                        'module'      => $file->module,
                        // Flag para saber en el frontend si necesita input previo
                        'requires_input' => $this->requiresUserInput($file->code),
                    ];
                });

            $currentClub = Club::query()->select('id', 'name', 'code')->find($clubId);

            return Inertia::render('AdminClubs/Documents/Index', [
                'documents'   => $documents,
                'currentClub' => $currentClub,
            ]);
        } catch (\Exception $e) {
            return Inertia::render('AdminClubs/Documents/Index', [
                'documents'    => [],
                'currentClub'  => null,
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

            // Descarga la plantilla del storage a un archivo temporal
            $disk           = config('filesystems.default');
            $templateBytes  = Storage::disk($disk)->get($clubFile->file_path);
            $tempTemplate   = tempnam(sys_get_temp_dir(), 'tpl_') . '.docx';
            file_put_contents($tempTemplate, $templateBytes);

            // Construye las variables según el formato
            $variables = $this->buildVariables($file, $clubId, $request);

            // Procesa la plantilla
            $processor = new TemplateProcessor($tempTemplate);
            foreach ($variables as $key => $value) {
                $processor->setValue($key, $value);
            }

            // Guarda el resultado
            $outputPath = tempnam(sys_get_temp_dir(), 'out_') . '.docx';
            $processor->saveAs($outputPath);

            // Limpia la plantilla temporal (el output se borra tras el send)
            @unlink($tempTemplate);

            $downloadName = Str::slug($file->name) . '-' . now()->format('Ymd-His') . '.docx';

            return response()->download($outputPath, $downloadName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
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
            'casillero' => true,
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
            'casillero' => [
                'folio'  => ClubFileCounter::nextFolio($clubId, $file->id),
                'genero' => $request->validate([
                    'genero' => 'required|in:DAMA,CABALLERO,NIÑO',
                ], [
                    'genero.required' => 'Debes seleccionar el género del casillero.',
                    'genero.in'       => 'El género no es válido.',
                ])['genero'],
            ],

            'constancia' => [
                'fecha' => now()->format('d/m/Y'),
            ],

            // Formato con solo folio consecutivo
            default => [
                'folio' => ClubFileCounter::nextFolio($clubId, $file->id),
            ],
        };
    }
}
