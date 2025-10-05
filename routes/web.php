<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderImportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\BankTransferController;
use App\Http\Controllers\PurchaseOrderController;

Route::get('/', fn() => redirect()->route('orders.import.create'));

Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/import', [OrderImportController::class, 'create'])->name('import.create');
    Route::post('/import', [OrderImportController::class, 'store'])->name('import.store');

    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('/{order}', [OrderImportController::class, 'show'])->name('show');

    Route::patch('/{order}', [OrderController::class, 'update'])->name('update');
    Route::delete('/{order}', [OrderController::class, 'destroy'])->name('destroy');

    Route::patch('/{order}/items/{item}', [OrderItemController::class, 'update'])->name('items.update');
    Route::delete('/{order}/items/{item}', [OrderItemController::class, 'destroy'])->name('items.destroy');
});

Route::prefix('bank')->name('bank.')->group(function () {
    Route::get('/', [BankTransferController::class, 'index'])->name('index');
    Route::get('/orders/{order}', [BankTransferController::class, 'show'])->name('show');
    Route::post('/orders/{order}/import', [BankTransferController::class, 'store'])->name('store');
    Route::delete('/orders/{order}/transfers/{transfer}', [BankTransferController::class, 'destroy'])->name('destroy');
});

// 注文書発行
Route::prefix('po')->name('po.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');         // 選択&入力画面
    Route::post('/generate', [PurchaseOrderController::class, 'generate'])->name('generate'); // DOCXを生成してDL
});

Route::get('/debug/zip', function () {
    return response()->json([
        'php'        => PHP_VERSION,
        'sapi'       => PHP_SAPI,
        'ini'        => php_ini_loaded_file(),
        'ext_dir'    => ini_get('extension_dir'),
        'ext_zip'    => extension_loaded('zip'),
        'class_zip'  => class_exists(\ZipArchive::class, false),
        'ext_gd'     => extension_loaded('gd'),
    ]);
});
