<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Services\Reports\Report;

/** Finance reports keep their own permissions (financial-reports.*). */
abstract class FinanceReport extends Report
{
    public function permission(): string
    {
        return 'financial-reports.view';
    }

    public function exportPermission(): string
    {
        return 'financial-reports.export';
    }
}
