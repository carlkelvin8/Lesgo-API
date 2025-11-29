<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Swagger docs JSON route (for L5-Swagger)
|--------------------------------------------------------------------------
| This serves storage/api-docs/api-docs.json
| Route name MUST be: l5-swagger.default.docs
|--------------------------------------------------------------------------
*/
Route::get('/docs', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (! file_exists($path)) {
        abort(404, 'Swagger docs file [api-docs.json] not found. Run: php artisan l5-swagger:generate');
    }

    return response()->file($path, [
        'Content-Type' => 'application/json',
    ]);
})->name('l5-swagger.default.docs');
