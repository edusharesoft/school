<?php

namespace App\Services\Reports\Definitions\Branches;

use App\Services\Dashboard\DashboardService;
use App\Services\Reports\Report;

/** Institution-wide comparison reports of the central area (every branch side by side). */
abstract class CentralReport extends Report
{
    public function __construct(protected DashboardService $dashboard) {}

    public function scope(): string
    {
        return 'central';
    }

    public function group(): string
    {
        return 'branches';
    }

    /** @return int[] */
    protected function branchIds(): array
    {
        return $this->dashboard->visibleBranchIds();
    }
}
