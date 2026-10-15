<?php

/*
|--------------------------------------------------------------------------
| Reports centre (Part 6e)
|--------------------------------------------------------------------------
| Every report is a class under app/Services/Reports/Definitions that builds a ReportResult. The centre shows it
| on screen and exports it as CSV, Excel (.xlsx, written without any library) or PDF (mPDF, see config/pdf.php).
| Large reports are prepared in the background (queue worker) and kept for a few days.
*/

return [

    // group key => label (menu order)
    'groups' => [
        'students' => 'Students',
        'attendance' => 'Attendance',
        'fees' => 'Fees',
        'accounts' => 'Accounts',
        'hr' => 'Staff',
        'library' => 'Library',
        'transport' => 'Transport',
        'hostel' => 'Hostel',
        'inventory' => 'Inventory',
        'assets' => 'Assets',
        'branches' => 'Branch comparison',
    ],

    'reports' => [
        \App\Services\Reports\Definitions\Library\IssuedBooksReport::class,
        \App\Services\Reports\Definitions\Library\OverdueBooksReport::class,
        \App\Services\Reports\Definitions\Library\CatalogueReport::class,
        \App\Services\Reports\Definitions\Library\FinesReport::class,
        \App\Services\Reports\Definitions\Library\MostIssuedReport::class,
        \App\Services\Reports\Definitions\Transport\RouteRosterReport::class,
        \App\Services\Reports\Definitions\Transport\VehicleComplianceReport::class,
        \App\Services\Reports\Definitions\Transport\VehicleCostReport::class,
        \App\Services\Reports\Definitions\Hostel\ResidentsReport::class,
        \App\Services\Reports\Definitions\Hostel\OccupancyReport::class,
        \App\Services\Reports\Definitions\Hostel\HostelAttendanceReport::class,
        \App\Services\Reports\Definitions\Hostel\VisitorsReport::class,
        \App\Services\Reports\Definitions\Inventory\StockReport::class,
        \App\Services\Reports\Definitions\Inventory\ConsumptionReport::class,
        \App\Services\Reports\Definitions\Inventory\PurchasesReport::class,
        \App\Services\Reports\Definitions\Assets\AssetRegisterReport::class,
        \App\Services\Reports\Definitions\Assets\AssetSummaryReport::class,
        \App\Services\Reports\Definitions\Assets\DepreciationReport::class,
        \App\Services\Reports\Definitions\Assets\AssetMaintenanceReport::class,
        \App\Services\Reports\Definitions\Finance\CollectionReport::class,
        \App\Services\Reports\Definitions\Finance\DuesReport::class,
        \App\Services\Reports\Definitions\Finance\DiscountsReport::class,
        \App\Services\Reports\Definitions\Finance\TrialBalanceReport::class,
        \App\Services\Reports\Definitions\Finance\IncomeExpenditureReport::class,
        \App\Services\Reports\Definitions\People\StudentListReport::class,
        \App\Services\Reports\Definitions\People\StaffListReport::class,
        \App\Services\Reports\Definitions\Attendance\StudentAttendanceReport::class,
        \App\Services\Reports\Definitions\Attendance\StaffAttendanceReport::class,
        \App\Services\Reports\Definitions\Branches\BranchAssetsReport::class,
        \App\Services\Reports\Definitions\Branches\BranchInventoryReport::class,
        \App\Services\Reports\Definitions\Branches\BranchFinanceReport::class,
    ],

    // Screen preview shows this many rows; exports contain all of them.
    'screen_rows' => 300,

    // A direct download is limited to this many rows; bigger reports are prepared in the background.
    'inline_max_rows' => 3000,

    // Hard stop for one export (rows). Narrow the filters beyond this.
    'max_rows' => 100000,

    // Finished background exports are deleted after this many days.
    'keep_days' => 7,

    // Exports a user may have waiting / running at the same time.
    'max_pending_per_user' => 3,

    // Private disk folder for finished export files.
    'disk' => 'local',
    'folder' => 'exports',
];
