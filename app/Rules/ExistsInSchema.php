<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class ExistsInSchema implements ValidationRule
{
    public function __construct(
        protected string $schema,
        protected string $table,
        protected string $column = 'id',
        protected array $wheres = []
    ) {}
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table("{$this->schema}.{$this->table}")
            ->where($this->column, $value)
            ->where($this->wheres)
            ->exists();

        if (!$exists) {
            $fail("The selected {$attribute} is invalid.");
        }
    }
}
