<?php

use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\TraceabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/catalogue', [PublicCatalogController::class, 'index'])->name('public.catalogue');
Route::get('/catalogue/products/{product}/image', [PublicCatalogController::class, 'productImage'])->whereNumber('product')->name('public.catalogue.product-image');
Route::get('/catalogue/products/{product}/compare', [PublicCatalogController::class, 'compareProduct'])->whereNumber('product')->name('public.products.compare');
Route::get('/catalogue/products/{product}', [PublicCatalogController::class, 'showProduct'])->whereNumber('product')->name('public.products.show');
Route::get('/p/{publicId}', [PublicCatalogController::class, 'showPublicProduct'])->whereUuid('publicId')->name('public.products.qr');
Route::get('/trace/{lot:public_id}', [PublicCatalogController::class, 'trace'])->name('public.trace');

// Keep old bookmarks working while moving the consumer catalogue to public access.
Route::get('/workspace/catalogue', fn () => redirect()->route('public.catalogue'))->name('legacy.workspace.catalogue');
Route::get('/workspace/catalogue/products/{product}/image', fn (int $product) => redirect()->route('public.catalogue.product-image', $product))->whereNumber('product')->name('legacy.workspace.catalogue.product-image');
Route::get('/workspace/catalogue/products/{product}', fn (int $product) => redirect()->route('public.products.show', $product))->whereNumber('product')->name('legacy.workspace.products.show');

Route::middleware(['auth', 'approved'])->prefix('workspace')->name('workspace.')->group(function () {
    Route::middleware('role:transformateur')->prefix('open-food-facts')->name('open-food-facts.')->group(function () {
        Route::get('/', [App\Http\Controllers\OpenFoodFactsController::class, 'index'])->name('index');
        Route::get('/search', [App\Http\Controllers\OpenFoodFactsController::class, 'search'])->middleware('throttle:20,1')->name('search');
        Route::post('/import', [App\Http\Controllers\OpenFoodFactsController::class, 'import'])->name('import');
    });
    Route::get('/qr-codes', [App\Http\Controllers\ProducerQrCodeController::class, 'index'])->middleware('role:producteur')->name('qr-codes.index');
    Route::get('/map-distances', [App\Http\Controllers\MapDistancesController::class, 'index'])->middleware('role:distributeur')->name('map-distances');
    Route::get('/{resource}', [TraceabilityController::class, 'index'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.index');
    Route::get('/{resource}/create', [TraceabilityController::class, 'create'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.create');
    Route::post('/{resource}', [TraceabilityController::class, 'store'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.store');
    Route::get('/{resource}/{record}/edit', [TraceabilityController::class, 'edit'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.edit');
    Route::get('/certifications/{record}/document', [TraceabilityController::class, 'certificationDocument'])->whereNumber('record')->name('certifications.document');
    Route::get('/farms/{record}/document', [TraceabilityController::class, 'farmDocument'])->whereNumber('record')->name('farms.document');
    Route::get('/{resource}/{record}/document', [TraceabilityController::class, 'traceabilityDocument'])->whereIn('resource', ['lots', 'transformations'])->whereNumber('record')->name('resources.document');
    Route::get('/{resource}/export', [TraceabilityController::class, 'export'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.export');
    Route::put('/{resource}/{record}', [TraceabilityController::class, 'update'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.update');
    Route::delete('/{resource}/{record}', [TraceabilityController::class, 'destroy'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.destroy');
    Route::get('/{resource}/{record}', [TraceabilityController::class, 'show'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.show');
});
