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

Route::prefix('{{ route_uri }}')->name('{{ route_name }}.'){{ middleware_code }}->group(function () {
    Route::get('information', [{{ controller_class }}::class, 'information'])->name('information');
});