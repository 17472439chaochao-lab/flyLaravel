<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ request_namespace }};

use Illuminate\Foundation\Http\FormRequest;

class {{ request_class }} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{{ rules_code }}
        ];
    }

    public function attributes(): array
    {
        return [
{{ attributes_code }}
        ];
    }
}