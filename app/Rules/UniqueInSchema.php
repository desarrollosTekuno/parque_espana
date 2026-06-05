<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueInSchema implements ValidationRule
{
    public function __construct(
        protected string $schema,
        protected string $table,
        protected string $column = 'id',
        protected mixed $ignoreValue = null,
        protected string $ignoreColumn = 'id'
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table("{$this->schema}.{$this->table}")
            ->where($this->column, $value);

        if ($this->ignoreValue !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreValue);
        }

        if ($query->exists()) {
            $fail("El valor de :attribute ya está en uso.");
        }
    }
}
