<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use {{ model_namespace }}\{{ model_class }};

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection({{ model_class }}::DB_CONNECTION)->create({{ model_class }}::TABLE_NAME, function (Blueprint $table) {
            $table->charset = '{{ charset }}';
            $table->collation = '{{ charset }}_general_ci';
            $table->engine = '{{ engine }}';

            $table->increments('id')->comment('表ID');
{{ fields_code }}

            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
{{#if index_code}}
{{ index_code }}
{{/if}}
        });
    }

    public function down(): void
    {
        Schema::connection({{ model_class }}::DB_CONNECTION)->dropIfExists({{ model_class }}::TABLE_NAME);
    }
};