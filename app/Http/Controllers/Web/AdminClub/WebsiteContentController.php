<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
use App\Models\Website\CarouselImage;
use App\Models\Website\HomeCard;
use App\Models\Website\VirtualTourCategory;
use App\Models\Website\VirtualTourImage;
use App\Models\Website\WebsiteEvent;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WebsiteContentController extends Controller {

    private const EVENT_TYPES = [
        ['value' => 'activity', 'title' => 'Actividad', 'color' => '#0097A7'],
        ['value' => 'celebration', 'title' => 'Celebración', 'color' => '#EC659C'],
        ['value' => 'holiday', 'title' => 'Día festivo', 'color' => '#F4B400'],
    ];

    public function __construct() {
        $this->middleware('permission:website-content.index')->only('index');
        $this->middleware('permission:website-content.store')->only([
            'store',
            'storeCard',
            'storeVirtualTourImages',
            'saveEvent',
        ]);
        $this->middleware('permission:website-content.destroy')->only([
            'destroy',
            'destroyCard',
            'destroyVirtualTourImage',
            'destroyEvent',
        ]);
    }

    public function index() {
        $clubId = (int) session('club_id');
        $club = Club::findOrFail($clubId);
        $virtualTourConfig = config("website.virtual_tour.{$club->code}", []);

        foreach (array_keys($virtualTourConfig) as $category) {
            VirtualTourCategory::firstOrCreate([
                'club_id' => $clubId,
                'name' => $category,
            ]);
        }

        $images = CarouselImage::where('club_id', session('club_id'))
            ->orderBy('id')
            ->get();

        $homeCards = HomeCard::where('club_id', session('club_id'))
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        $virtualTourCategories = VirtualTourCategory::where('club_id', $clubId)
            ->with('images')
            ->get()
            ->keyBy('name');

        $virtualTourSections = collect($virtualTourConfig)->map(function ($titles, $categoryName) use ($virtualTourCategories) {
            $category = $virtualTourCategories->get($categoryName);

            return [
                'name' => $categoryName,
                'slots' => collect($titles)->map(function ($title) use ($category) {
                    return [
                        'title' => $title,
                        'image' => $category?->images->firstWhere('title', $title),
                    ];
                })->values(),
            ];
        })->values();

        $events = WebsiteEvent::where('club_id', $clubId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('AdminClubs/WebsiteContent/Index', [
            'carouselImages' => $images,
            'homeCards' => $homeCards,
            'virtualTourSections' => $virtualTourSections,
            'events' => $events,
            'eventTypes' => self::EVENT_TYPES,
        ]);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=1200,min_height=800',
            ],
            'descriptions' => ['nullable', 'array', 'max:5'],
            'descriptions.*' => ['nullable', 'string', 'max:100'],
        ], [
            'images.required' => 'Selecciona al menos una imagen.',
            'images.max' => 'El carrusel puede tener máximo 5 imágenes.',
            'images.*.image' => 'Uno de los archivos no es una imagen válida.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WebP.',
            'images.*.max' => 'Cada imagen debe pesar máximo 5 MB.',
            'images.*.dimensions' => 'Cada imagen debe medir al menos 1200 × 800 px.',
            'descriptions.*.max' => 'Cada descripción debe tener máximo 100 caracteres.',
        ]);

        $clubId = (int) session('club_id');

        if (CarouselImage::where('club_id', $clubId)->count() + count($validated['images']) > 5) {
            return back()->withErrors([
                'images' => 'El carrusel puede tener máximo 5 imágenes. Elimina una existente para agregar otra.',
            ]);
        }

        $club = Club::findOrFail($clubId);
        $uploadedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($request->file('images') as $index => $image) {
                $path = $this->uploadImage($image, $club->code, 'carousel');
                $uploadedPaths[] = $path;

                CarouselImage::create([
                    'club_id' => $clubId,
                    'image_path' => $path,
                    'description' => $validated['descriptions'][$index] ?? null,
                ]);
            }

            DB::commit();

            return back()->with('success', 'Imágenes guardadas correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($uploadedPaths as $path) {
                Storage::disk('spaces')->delete($path);
            }

            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudieron guardar las imágenes.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function storeCard(Request $request) {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:30'],
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=750,min_height=1000',
            ],
        ], [
            'category.required' => 'Escribe el nombre de la categoría.',
            'category.max' => 'La categoría debe tener máximo 30 caracteres.',
            'image.required' => 'Selecciona una imagen.',
            'image.image' => 'El archivo seleccionado no es una imagen válida.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'image.max' => 'La imagen debe pesar máximo 5 MB.',
            'image.dimensions' => 'La imagen debe medir al menos 750 × 1000 px.',
        ]);

        $clubId = (int) session('club_id');

        if (HomeCard::where('club_id', $clubId)->count() >= 8) {
            return back()->withErrors([
                'category' => 'Solo puedes registrar un máximo de 8 cards de inicio. Elimina una para agregar otra.',
            ]);
        }

        $club = Club::findOrFail($clubId);
        $uploadedPath = null;

        DB::beginTransaction();

        try {
            $uploadedPath = $this->uploadImage(
                $request->file('image'),
                $club->code,
                'home-cards',
                750,
                1000
            );

            HomeCard::create([
                'club_id' => $clubId,
                'category' => $validated['category'],
                'image_path' => $uploadedPath,
            ]);

            DB::commit();

            return back()->with('success', 'Categoría guardada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($uploadedPath) {
                Storage::disk('spaces')->delete($uploadedPath);
            }

            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo guardar la categoría.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroyCard(int $id) {
        try {
            $card = HomeCard::where('club_id', session('club_id'))->findOrFail($id);

            if (Storage::disk('spaces')->exists($card->image_path)) {
                Storage::disk('spaces')->delete($card->image_path);
            }

            $card->delete();

            return back()->with('success', 'Card eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar la card.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function storeVirtualTourImages(Request $request) {
        $validated = $request->validate([
            'category' => ['required', 'string'],
            'title' => ['required', 'string'],
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=1200,min_height=800',
            ],
        ], [
            'image.required' => 'Selecciona una imagen.',
            'image.image' => 'El archivo seleccionado no es una imagen válida.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'image.max' => 'La imagen debe pesar máximo 5 MB.',
            'image.dimensions' => 'La imagen debe medir al menos 1200 × 800 px.',
        ]);

        $clubId = (int) session('club_id');
        $club = Club::findOrFail($clubId);
        $virtualTourConfig = config("website.virtual_tour.{$club->code}", []);

        if (!isset($virtualTourConfig[$validated['category']]) || !in_array($validated['title'], $virtualTourConfig[$validated['category']])) {
            return back()->withErrors([
                'messageError' => 'La sección seleccionada no es válida para este parque.',
            ]);
        }

        $category = VirtualTourCategory::firstOrCreate([
            'club_id' => $clubId,
            'name' => $validated['category'],
        ]);
        $image = $category->images()->where('title', $validated['title'])->first();
        $uploadedPath = null;
        $previousPath = $image?->image_path;

        DB::beginTransaction();

        try {
            $uploadedPath = $this->uploadImage($request->file('image'), $club->code, 'virtual-tour');

            if ($image) {
                $image->update(['image_path' => $uploadedPath]);
            } else {
                VirtualTourImage::create([
                    'category_id' => $category->id,
                    'title' => $validated['title'],
                    'image_path' => $uploadedPath,
                ]);
            }

            DB::commit();

            if ($previousPath && Storage::disk('spaces')->exists($previousPath)) {
                Storage::disk('spaces')->delete($previousPath);
            }

            return back()->with('success', 'Imagen guardada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($uploadedPath) {
                Storage::disk('spaces')->delete($uploadedPath);
            }

            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo guardar la imagen.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroyVirtualTourImage(int $id) {
        try {
            $image = VirtualTourImage::whereHas('category', function ($query) {
                $query->where('club_id', session('club_id'));
            })->findOrFail($id);

            if (Storage::disk('spaces')->exists($image->image_path)) {
                Storage::disk('spaces')->delete($image->image_path);
            }

            $image->delete();

            return back()->with('success', 'Imagen eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar la imagen.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function saveEvent(Request $request) {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', 'in:activity,celebration,holiday'],
        ], [
            'title.required' => 'Escribe el título del evento.',
            'title.max' => 'El título debe tener máximo 100 caracteres.',
            'start_date.required' => 'Selecciona la fecha de inicio del evento.',
            'end_date.required' => 'Selecciona la fecha de fin del evento.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'type.required' => 'Selecciona el tipo de evento.',
        ]);

        $clubId = (int) session('club_id');

        if (! empty($validated['id'])) {
            $event = WebsiteEvent::where('club_id', $clubId)->findOrFail($validated['id']);
            $event->update([
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'type' => $validated['type'],
            ]);

            return back()->with('success', 'Evento actualizado correctamente.');
        }

        WebsiteEvent::create([
            'club_id' => $clubId,
            'title' => $validated['title'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'type' => $validated['type'],
        ]);

        return back()->with('success', 'Evento guardado correctamente.');
    }

    public function destroyEvent(int $id)
    {
        try {
            $event = WebsiteEvent::where('club_id', session('club_id'))->findOrFail($id);
            $event->delete();

            return back()->with('success', 'Evento eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar el evento.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(int $id)
    {
        try {
            $image = CarouselImage::where('club_id', session('club_id'))->findOrFail($id);

            if (Storage::disk('spaces')->exists($image->image_path)) {
                Storage::disk('spaces')->delete($image->image_path);
            }

            $image->delete();

            return back()->with('success', 'Imagen eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar la imagen.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function uploadImage(
        $file,
        string $clubCode,
        string $folder,
        int $targetWidth = 1200,
        int $targetHeight = 800
    ): string {
        $source = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if (! $source) {
            throw new \Exception('La imagen no es válida.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetRatio = $targetWidth / $targetHeight;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = (int) round(($sourceWidth - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $destination = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        $temporaryPath = tempnam(sys_get_temp_dir(), 'carousel-');

        if (! $temporaryPath || ! imagewebp($destination, $temporaryPath, 82)) {
            imagedestroy($source);
            imagedestroy($destination);
            throw new \Exception('No se pudo convertir la imagen a WebP.');
        }

        $directory = "clubs/{$clubCode}/website/{$folder}";
        $filename = Str::uuid().'.webp';

        $stored = Storage::disk('spaces')->putFileAs(
            $directory,
            new File($temporaryPath),
            $filename,
            'public'
        );

        if (! $stored) {
            @unlink($temporaryPath);
            imagedestroy($source);
            imagedestroy($destination);
            throw new \Exception('No se pudo guardar la imagen.');
        }

        @unlink($temporaryPath);
        imagedestroy($source);
        imagedestroy($destination);

        return "{$directory}/{$filename}";
    }
}
