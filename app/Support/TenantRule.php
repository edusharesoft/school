<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules that can only see the current branch's rows, so a posted id
 * belonging to another branch fails validation (defence in depth next to BranchScope).
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id', bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, $column)->where('branch_id', app(TenantContext::class)->branchId());

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }

    public static function unique(string $table, string $column, mixed $ignoreId = null): Unique
    {
        return Rule::unique($table, $column)->where('branch_id', app(TenantContext::class)->branchId())->ignore($ignoreId);
    }
}
