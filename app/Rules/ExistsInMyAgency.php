<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Like "exists", but looked up through the model, so the signed-in user's agency filter applies:
 * a record of the other agency does not exist as far as validation is concerned.
 */
final class ExistsInMyAgency implements ValidationRule
{
    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    public function __construct(private readonly string $model) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_numeric($value) || ! $this->model::query()->whereKey((int) $value)->exists()) {
            $fail('The selected :attribute is not valid.');
        }
    }
}
