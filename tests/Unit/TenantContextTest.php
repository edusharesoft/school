<?php

namespace Tests\Unit;

use App\Enums\TenantMode;
use App\Models\Branch;
use App\Models\Institution;
use App\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    private function branch(int $id, int $institutionId): Branch
    {
        $institution = (new Institution)->forceFill(['id' => $institutionId]);
        $branch = (new Branch)->forceFill(['id' => $id, 'institution_id' => $institutionId]);
        $branch->setRelation('institution', $institution);

        return $branch;
    }

    public function test_default_mode_is_none(): void
    {
        $context = new TenantContext;

        $this->assertSame(TenantMode::None, $context->mode());
        $this->assertSame('none', $context->key());
    }

    public function test_run_for_branch_restores_previous_state(): void
    {
        $context = (new TenantContext)->setCentral(null);

        $inside = $context->runForBranch($this->branch(7, 2), fn () => [$context->key(), $context->institutionId()]);

        $this->assertSame(['branch:7', 2], $inside);
        $this->assertTrue($context->isCentral());
        $this->assertSame('central', $context->key());
    }

    public function test_state_is_restored_even_when_callback_throws(): void
    {
        $context = new TenantContext;

        try {
            $context->runForBranch($this->branch(3, 1), fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }

        $this->assertSame(TenantMode::None, $context->mode());
    }
}
