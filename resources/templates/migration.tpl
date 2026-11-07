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
            $table->collation = '{{ collation }}';
            $table->engine = '{{ engine }}';

{{ id_code }}
{{ fields_code }}

{{#if has_timestamps}}
{{ timestamps_code }}
{{/if}}
{{#if has_soft_deletes}}
{{ soft_deletes_code }}
{{/if}}
{{#if index_code}}
{{ index_code }}
{{/if}}
{{#if fulltext_code}}
{{ fulltext_code }}
{{/if}}
{{#if foreign_code}}
{{ foreign_code }}
{{/if}}
        });
    }

    public function down(): void
    {
        Schema::connection({{ model_class }}::DB_CONNECTION)->dropIfExists({{ model_class }}::TABLE_NAME);
    }
};
