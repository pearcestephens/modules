<?php
declare(strict_types=1);

namespace Modules\Base\Controller;

use Modules\Base\Validation;

abstract class ApiController extends BaseController
{
    protected function ok(array $data = []): array
    {
        return ['ok' => true] + $data;
    }

    protected function fail(string $error, array $errors = []): array
    {
        return ['ok' => false, 'error' => $error] + (!empty($errors) ? ['errors' => $errors] : []);
    }

    /**
     * Validate using Shared\Validation rules.
     * @return array{0:bool,1:array}
     */
    protected function validate(array $data, array $rules): array
    {
        return Validation::validate($data, $rules);
    }
}
