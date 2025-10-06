<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderImportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\BankTransferController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\LabelExportController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\Settings\EmailTemplateController;
use App\Http\Controllers\IncomeController;

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
    Route::get('/',                   [BankTransferController::class, 'index'])->name('index');
    Route::get('/{order}',            [BankTransferController::class, 'show'])->name('show');
    Route::post('/{order}',           [BankTransferController::class, 'store'])->name('store');
    Route::delete('/{order}/{transfer}', [BankTransferController::class, 'destroy'])->name('destroy');
});

// 注文書発行
Route::prefix('po')->name('po.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');         // 選択&入力画面
    Route::post('/generate', [PurchaseOrderController::class, 'generate'])->name('generate'); // DOCXを生成してDL
});

// 送り状発行（一覧/出力）
Route::get('/labels',         [LabelExportController::class, 'index'])->name('labels.index');
Route::post('/labels/export', [LabelExportController::class, 'export'])->name('labels.export');

// 追跡番号の登録（アイテム単位）
Route::patch('/order-items/{item}/tracking', [TrackingController::class, 'update'])
    ->name('order-items.tracking.update');

Route::get('/shipping',            [ShippingController::class, 'index'])->name('shipping.index');
Route::post('/shipping/dispatch',  [ShippingController::class, 'dispatch'])->name('shipping.dispatch');
Route::post('/shipping/save-tracking', [ShippingController::class, 'saveTracking'])->name('shipping.saveTracking');

Route::get('/settings/mail',  [EmailTemplateController::class, 'edit'])->name('settings.mail.edit');
Route::patch('/settings/mail', [EmailTemplateController::class, 'update'])->name('settings.mail.update');

Route::get('/income',               [IncomeController::class, 'index'])->name('income.index');
Route::get('/income/{ym}',          [IncomeController::class, 'show'])
    ->where('ym', '^\d{4}-\d{2}$')->name('income.show');

// エクスポート（JSON を返す。Excel作成は任せる想定）
Route::get('/income/{ym}/export',   [IncomeController::class, 'export'])
    ->where('ym', '^\d{4}-\d{2}$')->name('income.export');
Route::get('/income/{ym}/export/xlsx', [IncomeController::class,'exportXlsx'])
    ->where('ym','^\d{4}-\d{2}$')->name('income.export.xlsx');
Route::get('/income/export/all', [IncomeController::class, 'exportAllXlsx'])
    ->name('income.export.all');

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
