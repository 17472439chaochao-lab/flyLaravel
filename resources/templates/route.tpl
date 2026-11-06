<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

use Illuminate\Support\Facades\Route;
use {{ controller_namespace }}\{{ controller_class }};

Route::prefix('{{ route_uri }}')->name('{{ route_name }}.')->group(function () {
    Route::get('/', [{{ controller_class }}::class, 'index'])->name('index');
    Route::get('/{id}', [{{ controller_class }}::class, 'show'])->name('show');
    Route::post('/', [{{ controller_class }}::class, 'store'])->name('store');
    Route::put('/{id}', [{{ controller_class }}::class, 'update'])->name('update');
    Route::delete('/{id}', [{{ controller_class }}::class, 'destroy'])->name('destroy');
});