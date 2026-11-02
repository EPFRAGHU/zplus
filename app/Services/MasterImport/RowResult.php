<?php

namespace App\Services\MasterImport;

use Illuminate\Database\Eloquent\Model;

/** What one Excel row will do: create, update, nothing, or fail with errors. */
final class RowResult
{
    public const NEW = 'new';
    public const UPDATE = 'update';
    public const UNCHANGED = 'unchanged';
    public const ERROR = 'error';

    /** @param  array<string, mixed>  $data  attributes to save */
    public function __construct(
        public readonly int $row,
        public string $status,
        public string $label,
        public array $data = [],
        public ?Model $model = null,
        public array $errors = [],
        public array $changes = [],
    ) {}
}
