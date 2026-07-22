<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueInSchema implements ValidationRule
{
    protected mixed $ignoreValue = null;
    protected string $ignoreColumn = 'id';
    protected array $wheres = [];

    public function __construct(
        protected string $schema,
        protected string $table,
        protected string $column = 'id'
    ) {}

    public function ignore(mixed $value, string $column = 'id'): static
    {
        $this->ignoreValue  = $value;
        $this->ignoreColumn = $column;

        return $this;
    }

    public function where(string $column, mixed $value): static
    {
        $this->wheres[$column] = $value;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table("{$this->schema}.{$this->table}")
            ->where($this->column, $value);

        foreach ($this->wheres as $col => $val) {
            $query->where($col, $val);
        }

        if ($this->ignoreValue !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreValue);
        }

        if ($query->exists()) {
            $fail("El valor del campo {$attribute} ya está en uso.");
        }
    }
}
