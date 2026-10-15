<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Models\Academic\Room;
use App\Models\Assets\Category;
use App\Models\Department;
use App\Models\Finance\Account;
use App\Models\Finance\Vendor;
use App\Models\HR\Employee;

/** Drop-down lists shared by the asset screens. */
trait AssetLookups
{
    protected function categories(bool $onlyActive = true): \Illuminate\Support\Collection
    {
        return Category::query()->when($onlyActive, fn ($q) => $q->where('is_active', true))->orderBy('name')->get();
    }

    protected function employees(): array
    {
        return Employee::query()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function departments(): array
    {
        return Department::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function rooms(): array
    {
        return Room::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function vendors(): array
    {
        return Vendor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function moneyAccounts(): array
    {
        return Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all();
    }
}
