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
{{#if has_enum}}
use Illuminate\Validation\Rule;
{{/if}}

class {{ request_class }} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
{{ rules_code }}
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = array_map(static function (array $fieldRules): array {
                return array_values(array_filter($fieldRules, static fn (string $rule): bool => $rule !== 'required'));
            }, $rules);
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
{{ attributes_code }}
        ];
    }
}