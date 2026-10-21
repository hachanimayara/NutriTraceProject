<?php

use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\ProductRatingController;
use App\Http\Controllers\TraceabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/catalogue', [PublicCatalogController::class, 'index'])->name('public.catalogue');
Route::get('/catalogue/products/{product}/image', [PublicCatalogController::class, 'productImage'])->whereNumber('product')->name('public.catalogue.product-image');
Route::get('/catalogue/products/{product}/compare', [PublicCatalogController::class, 'compareProduct'])->whereNumber('product')->name('public.products.compare');
Route::get('/catalogue/products/{product}', [PublicCatalogController::class, 'showProduct'])->whereNumber('product')->name('public.products.show');
Route::get('/p/{publicId}', [PublicCatalogController::class, 'showPublicProduct'])->whereUuid('publicId')->name('public.products.qr');
Route::get('/trace/{lot:public_id}', [PublicCatalogController::class, 'trace'])->name('public.trace');

Route::post('/catalogue/products/{product}/ratings', [ProductRatingController::class, 'store'])
    ->middleware('throttle:5,1')->whereNumber('product')->name('consumer.product-ratings.store');

// Keep old bookmarks working while moving the consumer catalogue to public access.
Route::get('/workspace/catalogue', fn () => redirect()->route('public.catalogue'))->name('legacy.workspace.catalogue');
Route::get('/workspace/catalogue/products/{product}/image', fn (int $product) => redirect()->route('public.catalogue.product-image', $product))->whereNumber('product')->name('legacy.workspace.catalogue.product-image');
Route::get('/workspace/catalogue/products/{product}', fn (int $product) => redirect()->route('public.products.show', $product))->whereNumber('product')->name('legacy.workspace.products.show');

Route::middleware(['auth', 'approved'])->prefix('workspace')->name('workspace.')->group(function () {
    Route::middleware('role:transformateur')->prefix('open-food-facts')->name('open-food-facts.')->group(function () {
        Route::get('/', [App\Http\Controllers\OpenFoodFactsController::class, 'index'])->name('index');
        Route::get('/search', [App\Http\Controllers\OpenFoodFactsController::class, 'search'])->middleware('throttle:20,1')->name('search');
        Route::post('/import', [App\Http\Controllers\OpenFoodFactsController::class, 'import'])->name('import');
        Route::delete('/items/{item}', [App\Http\Controllers\OpenFoodFactsController::class, 'destroyData'])->whereNumber('item')->name('items.destroy');
    });
    Route::get('/qr-codes', [App\Http\Controllers\ProducerQrCodeController::class, 'index'])->middleware('role:producteur')->name('qr-codes.index');
    Route::get('/map-distances', [App\Http\Controllers\MapDistancesController::class, 'index'])->middleware('role:distributeur')->name('map-distances');
    Route::middleware('role:distributeur')->prefix('history')->name('history.')->group(function () {
        Route::get('/', [App\Http\Controllers\DistributorHistoryController::class, 'index'])->name('index');
        Route::get('/{distribution}', [App\Http\Controllers\DistributorHistoryController::class, 'show'])->whereNumber('distribution')->name('show');
    });
    Route::get('/{resource}', [TraceabilityController::class, 'index'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.index');
    Route::get('/{resource}/create', [TraceabilityController::class, 'create'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.create');
    Route::post('/{resource}', [TraceabilityController::class, 'store'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.store');
    Route::get('/{resource}/{record}/edit', [TraceabilityController::class, 'edit'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.edit');
    Route::get('/certifications/{record}/document', [TraceabilityController::class, 'certificationDocument'])->whereNumber('record')->name('certifications.document');
    Route::get('/farms/{record}/document', [TraceabilityController::class, 'farmDocument'])->whereNumber('record')->name('farms.document');
    Route::get('/{resource}/{record}/document', [TraceabilityController::class, 'traceabilityDocument'])->whereIn('resource', ['lots', 'transformations'])->whereNumber('record')->name('resources.document');
    Route::get('/{resource}/export', [TraceabilityController::class, 'export'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.export');
    Route::get('/{resource}/import', [TraceabilityController::class, 'importPage'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.import');
    Route::post('/{resource}/import', [TraceabilityController::class, 'analyzeImport'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.import.analyze');
    Route::get('/{resource}/import-template', [TraceabilityController::class, 'importTemplate'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.import-template');
    Route::post('/{resource}/import-row', [TraceabilityController::class, 'useImportRow'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->name('resources.import-row');
    Route::put('/{resource}/{record}', [TraceabilityController::class, 'update'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.update');
    Route::delete('/{resource}/{record}', [TraceabilityController::class, 'destroy'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.destroy');
    Route::get('/{resource}/{record}', [TraceabilityController::class, 'show'])->whereIn('resource', ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'])->whereNumber('record')->name('resources.show');
});

Route::middleware(['auth', 'approved', 'role:producteur'])->prefix('producer/harvest-plans')->name('producer.harvest-plans.')->group(function () {
    Route::get('/', [App\Http\Controllers\HarvestPlanController::class, 'index'])->name('index');
    Route::get('/events', [App\Http\Controllers\HarvestPlanController::class, 'events'])->name('events');
    Route::get('/create', [App\Http\Controllers\HarvestPlanController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\HarvestPlanController::class, 'store'])->name('store');
    Route::get('/{harvestPlan}/edit', [App\Http\Controllers\HarvestPlanController::class, 'edit'])->whereNumber('harvestPlan')->name('edit');
    Route::put('/{harvestPlan}', [App\Http\Controllers\HarvestPlanController::class, 'update'])->whereNumber('harvestPlan')->name('update');
    Route::patch('/{harvestPlan}/status', [App\Http\Controllers\HarvestPlanController::class, 'status'])->whereNumber('harvestPlan')->name('status');
    Route::delete('/{harvestPlan}', [App\Http\Controllers\HarvestPlanController::class, 'destroy'])->whereNumber('harvestPlan')->name('destroy');
    Route::get('/{harvestPlan}', [App\Http\Controllers\HarvestPlanController::class, 'show'])->whereNumber('harvestPlan')->name('show');
});

Route::middleware(['auth', 'approved', 'role:producteur'])->prefix('producer/weather')->name('producer.weather.')->group(function () {
    Route::get('/', [App\Http\Controllers\ProducerWeatherController::class, 'index'])->name('index');
    Route::get('/forecast', [App\Http\Controllers\ProducerWeatherController::class, 'forecast'])->middleware('throttle:30,1')->name('forecast');
});
