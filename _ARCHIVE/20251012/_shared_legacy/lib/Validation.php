<?php
declare(strict_types=1);

namespace Modules\Shared;

final class Validation
{
    /**
     * Validate $data against $rules like ['field' => 'required|int|min:1'].
     * Returns [bool ok, array errors].
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $value = $data[$field] ?? null;
            $rulesArr = explode('|', (string)$ruleStr);
            foreach ($rulesArr as $rule) {
                $rule = trim($rule);
                if ($rule === 'required') {
                    if ($value === null || $value === '' ) {
                        $errors[$field][] = 'required';
                    }
                } elseif ($rule === 'int') {
                    if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $errors[$field][] = 'int';
                    }
                } elseif ($rule === 'uuid') {
                    // Optional field: only validate format when present
                    if ($value !== null && $value !== '') {
                        $v = (string)$value;
                        if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $v)) {
                            $errors[$field][] = 'uuid';
                        }
                    }
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (int)substr($rule, 4);
                    if ((int)$value < $min) {
                        $errors[$field][] = 'min:' . $min;
                    }
                }
            }
        }
        return [empty($errors), $errors];
    }
}
