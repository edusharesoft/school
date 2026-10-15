<?php

namespace Tests\Unit;

use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\TestCase;

class RolePatternTest extends TestCase
{
    private array $all = ['students.view', 'students.create', 'fees.view', 'fees.create', 'payroll.view', 'payroll.process'];

    public function test_wildcard_all(): void
    {
        $this->assertSame($this->all, RolesAndPermissionsSeeder::expandPatterns(['*'], $this->all));
    }

    public function test_module_wildcard(): void
    {
        $this->assertSame(['fees.view', 'fees.create'], RolesAndPermissionsSeeder::expandPatterns(['fees.*'], $this->all));
    }

    public function test_exclusions_apply_after_inclusions(): void
    {
        $this->assertSame(
            ['students.view', 'students.create', 'fees.view', 'fees.create'],
            RolesAndPermissionsSeeder::expandPatterns(['*', '!payroll.*'], $this->all)
        );
    }

    public function test_unknown_permissions_are_ignored(): void
    {
        $this->assertSame([], RolesAndPermissionsSeeder::expandPatterns(['library.view'], $this->all));
    }
}
