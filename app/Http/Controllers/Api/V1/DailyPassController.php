<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class DailyPassController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request) {
        try {
            $validated = $request->validate([

            ]);

        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al crear los pases diarios.');
        }
    }

    public function MyFunction(Request $request) {
        return "MyFunction";
    }
}
