<?php

namespace Tests\Feature;

use App\Models\Assets\Category as AssetCategory;
use App\Models\Branch;
use App\Models\Hostel\Attendance as HostelAttendance;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\Room;
use App\Models\Hostel\Visitor;
use App\Models\Inventory\Category as StockCategory;
use App\Models\Inventory\Item;
use App\Models\Library\LibraryBook;
use App\Models\Reports\ExportRun;
use App\Models\Transport\FuelLog;
use App\Models\Transport\Maintenance;
use App\Models\Transport\Stop;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\Vehicle;
use App\Models\User;
use App\Services\Assets\AssetService;
use App\Services\Documents\PdfService;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Hostel\HostelService;
use App\Services\Inventory\InventoryService;
use App\Services\Library\LibraryService;
use App\Services\Reports\Exporters\CsvExporter;
use App\Services\Reports\Exporters\XlsxWriter;
use App\Services\Reports\ExportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportResult;
use App\Services\Transport\TransportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Part 6e — reports centre: every report runs, exports (CSV / Excel / PDF), background exports, permissions, isolation. */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected PdfService $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
        $this->pdf = new class extends PdfService
        {
            public array $rendered = [];

            public function available(): bool
            {
                return true;
            }

            public function render(string $html, array $options = []): string
            {
                $this->rendered[] = ['html' => $html, 'options' => $options];

                return '%PDF-1.4 recorded';
            }
        };
        $this->app->instance(PdfService::class, $this->pdf);
    }

    /** A branch with a little of everything: students, staff, a loan, a route, a bed, stock and assets. */
    protected function world(): array
    {
        $branch = $this->makeBranch(null, ['name' => 'Reports Branch']);
        $admin = $this->branchUser($branch, 'branch-admin');
        $librarian = $this->branchUser($branch, 'librarian');
        $setup = $this->academicSetup($branch);
        $anika = $this->enrolStudent($branch, $setup, 'Anika Rahman');
        $babul = $this->enrolStudent($branch, $setup, 'Babul Hasan');
        $teacher = $this->makeEmployee($branch, ['name' => 'Mr. Teacher']);

        $data = $this->inBranch($branch, function () use ($admin, $anika, $babul) {
            // library: one overdue loan with an unpaid fine
            $book = LibraryBook::query()->create(['title' => 'Padma Nadir Majhi', 'authors' => 'Manik Bandyopadhyay', 'language' => 'bn', 'price' => 200, 'is_active' => true]);
            $copy = app(LibraryService::class)->addCopies($book, 2)->first();
            $loan = app(LibraryService::class)->issue($copy, 'student', $anika->id, $admin);
            $loan->forceFill(['issued_on' => '2026-02-15', 'due_on' => '2026-03-01'])->save();      // a loan that is now two weeks late
            app(LibraryService::class)->charge('student', $anika->id, 'overdue', 70, 'late', $admin);

            // transport: a vehicle with fuel and a repair, a route with one rider
            $vehicle = Vehicle::query()->create(['registration_no' => 'DHA-1234', 'type' => 'bus', 'capacity' => 30, 'fuel_type' => 'diesel', 'status' => 'active', 'insurance_expiry' => '2026-03-20', 'fitness_expiry' => '2026-01-01']);
            $route = TransportRoute::query()->create(['code' => 'R1', 'name' => 'Mirpur line', 'vehicle_id' => $vehicle->id, 'monthly_fee' => 1000, 'is_active' => true]);
            $stop = Stop::query()->create(['transport_route_id' => $route->id, 'name' => 'Mirpur 10', 'sequence' => 1, 'pickup_time' => '07:15:00']);
            app(TransportService::class)->allocate($anika, $stop, null, '2026-03-01', $admin);
            FuelLog::query()->create(['transport_vehicle_id' => $vehicle->id, 'date' => '2026-03-05', 'litres' => 40, 'total_cost' => 4400]);
            Maintenance::query()->create(['transport_vehicle_id' => $vehicle->id, 'date' => '2026-03-08', 'kind' => 'repair', 'description' => 'Brake pads', 'cost' => 3000]);

            // hostel: one resident, a roll call and a visitor
            $hostel = Hostel::query()->create(['name' => 'Green Hostel', 'type' => 'mixed', 'is_active' => true]);
            $room = Room::query()->create(['hostel_id' => $hostel->id, 'room_no' => '101', 'type' => 'shared', 'monthly_fee' => 3000, 'is_active' => true]);
            $beds = collect([1, 2])->map(fn ($i) => $room->beds()->create(['label' => 'B'.$i]));
            app(HostelService::class)->allocate($babul, $beds->first(), null, '2026-03-01', $admin);
            HostelAttendance::query()->create(['hostel_id' => $hostel->id, 'student_id' => $babul->id, 'date' => '2026-03-10', 'status' => 'present']);
            HostelAttendance::query()->create(['hostel_id' => $hostel->id, 'student_id' => $babul->id, 'date' => '2026-03-11', 'status' => 'absent']);
            Visitor::query()->create(['hostel_id' => $hostel->id, 'student_id' => $babul->id, 'visitor_name' => 'Hasan Sr', 'relation' => 'father', 'in_at' => '2026-03-12 16:00:00', 'out_at' => '2026-03-12 17:30:00']);

            // inventory: stock with a value
            $cat = StockCategory::query()->create(['name' => 'Stationery', 'kind' => 'general', 'is_active' => true]);
            $item = app(InventoryService::class)->createItem(['name' => 'A4 paper', 'name_bn' => 'এ৪ কাগজ', 'inventory_category_id' => $cat->id, 'item_type' => 'consumable', 'unit' => 'ream', 'reorder_level' => 10, 'is_active' => true]);
            $item->forceFill(['quantity_on_hand' => 4, 'stock_value' => 1200])->save();

            // assets: one purchased asset
            $assetCat = AssetCategory::query()->create(['name' => 'Furniture', 'kind' => 'general', 'depreciation_method' => 'straight_line', 'useful_life_months' => 120, 'salvage_percent' => 0, 'is_active' => true]);
            $asset = app(AssetService::class)->register(['name' => 'Teacher table', 'asset_category_id' => $assetCat->id, 'acquisition_type' => 'purchase', 'acquired_on' => '2026-01-10',
                'in_service_on' => '2026-01-10', 'cost' => 12000, 'paid_from_account_id' => app(ChartOfAccounts::class)->id('cash'), 'condition' => 'new'], $admin);

            return compact('book', 'loan', 'vehicle', 'route', 'hostel', 'item', 'asset');
        });

        return ['branch' => $branch, 'admin' => $admin, 'librarian' => $librarian, 'setup' => $setup, 'anika' => $anika, 'babul' => $babul, 'teacher' => $teacher] + $data;
    }

    protected function url(array $w, string $path = ''): string
    {
        return $this->branchUrl($w['branch'], 'reports'.($path === '' ? '' : '/'.$path));
    }

    protected function as(array $w, ?User $user = null): static
    {
        return $this->actingOnBranch($user ?? $w['admin'], $w['branch']);
    }

    protected function runReport(array $w, string $key, array $filters = []): ReportResult
    {
        return $this->inBranch($w['branch'], function () use ($key, $filters) {
            $report = app(ReportRegistry::class)->find($key);
            $this->assertNotNull($report, "report $key is registered");

            return app(ExportService::class)->build($report, $filters);
        });
    }

    // ------------------------------------------------------------------ the reports themselves

    public function test_every_branch_report_runs_with_its_default_filters_and_is_well_formed(): void
    {
        $w = $this->world();
        $registry = app(ReportRegistry::class);
        $keys = $registry->all()->filter(fn (Report $r) => $r->scope() === 'branch')->keys();
        $this->assertGreaterThanOrEqual(25, $keys->count());

        foreach ($keys as $key) {
            $result = $this->runReport($w, $key);
            $columnKeys = array_column($result->columns, 'key');
            $this->assertNotEmpty($columnKeys, "$key has columns");
            $this->assertSame($columnKeys, array_values(array_unique($columnKeys)), "$key column keys are unique");
            foreach ($result->columns as $column) {
                $this->assertContains($column['type'], ReportResult::TYPES, "$key column {$column['key']} type");
                $this->assertNotSame('', trim($column['label']), "$key column label");
            }
            foreach ($result->rows as $i => $row) {
                $this->assertSame([], array_diff(array_keys($row), $columnKeys), "$key row $i has only declared columns");
            }
        }
    }

    public function test_module_reports_show_the_branch_data(): void
    {
        $w = $this->world();

        $issued = $this->runReport($w, 'library.issued', ['from' => '2026-02-01', 'to' => '2026-03-31']);
        $this->assertSame(1, $issued->rowCount());
        $this->assertSame('Padma Nadir Majhi', $issued->rows[0]['title']);
        $this->assertSame('Anika Rahman', $issued->rows[0]['member']);
        $this->assertSame(14, $issued->rows[0]['late']);

        $overdue = $this->runReport($w, 'library.overdue');
        $this->assertSame(1, $overdue->rowCount());
        $this->assertSame(14, $overdue->rows[0]['late']);
        $this->assertSame(0, $this->runReport($w, 'library.overdue', ['min_days' => 30])->rowCount());

        $fines = $this->runReport($w, 'library.fines', ['from' => '2026-03-01', 'to' => '2026-03-31']);
        $this->assertSame(70.0, $fines->totals()['owed']);
        $catalogue = $this->runReport($w, 'library.catalogue');
        $this->assertSame(2, $catalogue->rows[0]['total']);
        $this->assertSame(1, $catalogue->rows[0]['issued']);
        $this->assertSame(400.0, $catalogue->rows[0]['value']);
        $this->assertSame(1, $this->runReport($w, 'library.most-issued', ['from' => '2026-02-01', 'to' => '2026-03-31'])->rows[0]['loans']);

        $roster = $this->runReport($w, 'transport.roster');
        $this->assertSame('Anika Rahman', $roster->rows[0]['student']);
        $this->assertSame('07:15', $roster->rows[0]['pickup']);
        $this->assertSame(1000.0, $roster->totals()['fee']);
        $docs = $this->runReport($w, 'transport.compliance', ['attention' => 1]);
        $states = array_column($docs->rows, 'state', 'document');
        $this->assertSame(__('Expired'), $states[__('Fitness certificate')]);
        $this->assertSame(__('Expiring soon'), $states[__('Insurance')]);
        $costs = $this->runReport($w, 'transport.costs', ['from' => '2026-03-01', 'to' => '2026-03-31']);
        $this->assertSame(7400.0, $costs->rows[0]['total']);

        $residents = $this->runReport($w, 'hostel.residents');
        $this->assertSame('Babul Hasan', $residents->rows[0]['student']);
        $this->assertSame(3000.0, $residents->rows[0]['total']);
        $occupancy = $this->runReport($w, 'hostel.occupancy');
        $this->assertSame([2, 1, 1], [$occupancy->rows[0]['beds'], $occupancy->rows[0]['occupied'], $occupancy->rows[0]['vacant']]);
        $this->assertSame(50.0, (float) $occupancy->rows[0]['rate']);
        $roll = $this->runReport($w, 'hostel.attendance', ['from' => '2026-03-01', 'to' => '2026-03-31']);
        $this->assertSame([1, 1, 2], [$roll->rows[0]['present'], $roll->rows[0]['absent'], $roll->rows[0]['days']]);
        $this->assertSame(90, $this->runReport($w, 'hostel.visitors', ['from' => '2026-03-01', 'to' => '2026-03-31'])->rows[0]['minutes']);

        $stock = $this->runReport($w, 'inventory.stock');
        $this->assertSame(1200.0, $stock->totals()['value']);
        $this->assertSame(__('Low stock'), $stock->rows[0]['state']);
        $this->assertSame(300.0, $stock->rows[0]['avg']);
        $this->assertSame(1, $this->runReport($w, 'inventory.stock', ['status' => 'low'])->rowCount());
        $this->assertSame(0, $this->runReport($w, 'inventory.stock', ['status' => 'out'])->rowCount());

        $register = $this->runReport($w, 'assets.register');
        $this->assertSame(1, $register->rowCount());
        $this->assertSame(12000.0, $register->totals()['cost']);
        $this->assertSame(12000.0, $this->runReport($w, 'assets.summary')->totals()['cost']);

        $students = $this->runReport($w, 'students.list');
        $this->assertSame(['Anika Rahman', 'Babul Hasan'], array_column($students->rows, 'name'));
        $this->assertSame('Guardian of Anika Rahman', $students->rows[0]['guardian']);
        $this->assertSame(1, $this->runReport($w, 'students.list', ['q' => 'Babul'])->rowCount());
        $this->assertSame(1, $this->runReport($w, 'hr.staff-list')->rowCount());
    }

    public function test_reports_stay_inside_the_branch(): void
    {
        $w = $this->world();
        $other = $this->makeBranch(null, ['name' => 'Other Branch']);
        $this->inBranch($other, fn () => LibraryBook::query()->create(['title' => 'Secret Book', 'language' => 'bn', 'price' => 10, 'is_active' => true]));

        $titles = array_column($this->runReport($w, 'library.catalogue')->rows, 'title');
        $this->assertSame(['Padma Nadir Majhi'], $titles);
        $this->assertSame([], $this->inBranch($other, fn () => app(ExportService::class)->build(app(ReportRegistry::class)->find('students.list'), [])->rows));
    }

    public function test_filters_are_cleaned_dates_are_ordered_and_ranges_are_capped(): void
    {
        $w = $this->world();
        $report = app(ReportRegistry::class)->find('library.issued');

        $clean = $report->normalize(['from' => 'not-a-date', 'to' => '2026-02-30', 'status' => 'hacked', 'member_type' => 'student', 'extra' => 'x']);
        $this->assertSame(['from', 'to', 'status', 'member_type'], array_keys($clean));
        $this->assertSame('2026-03-01', $clean['from']);       // bad date → default (start of this month)
        $this->assertSame('2026-03-15', $clean['to']);
        $this->assertNull($clean['status']);                   // not an option
        $this->assertSame('student', $clean['member_type']);

        // from after to is swapped; a range over a year is cut to a year
        $issued = $this->runReport($w, 'library.issued', ['from' => '2026-03-31', 'to' => '2026-02-01']);
        $this->assertSame(1, $issued->rowCount());
        $wide = $this->inBranch($w['branch'], function () {
            $r = new class extends \App\Services\Reports\Definitions\Library\IssuedBooksReport
            {
                public function dates(array $f): array
                {
                    return $this->range($f);
                }
            };

            return $r->dates(['from' => '2000-01-01', 'to' => '2026-03-15']);
        });
        $this->assertSame('2025-03-14', $wide[0]->toDateString());
    }

    // ------------------------------------------------------------------ exporters

    protected function sample(): ReportResult
    {
        return new ReportResult('Sample report', [
            ReportResult::col('name', 'Name'), ReportResult::col('qty', 'Qty', 'int', true), ReportResult::col('amount', 'Amount', 'money', true),
            ReportResult::col('on', 'Date', 'date'), ReportResult::col('rate', 'Rate', 'percent'),
        ], [
            ['name' => 'বাংলা নাম & <b>tag</b>', 'qty' => 2, 'amount' => 1234.5, 'on' => '2026-03-05', 'rate' => 12.5],
            ['name' => '=HYPERLINK("http://x")', 'qty' => 3, 'amount' => 0.25, 'on' => null, 'rate' => null],
        ], 'A subtitle', ['From' => '01/03/2026'], ['Rows' => 2]);
    }

    public function test_csv_has_bom_header_totals_and_guards_formulas(): void
    {
        $stream = fopen('php://memory', 'w+');
        (new CsvExporter)->write($this->sample(), $stream);
        rewind($stream);
        $csv = stream_get_contents($stream);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', substr($csv, 3)))));
        $this->assertSame('Name,Qty,Amount,Date,Rate', $lines[0]);
        $this->assertSame('"বাংলা নাম & <b>tag</b>",2,1234.5,2026-03-05,12.5', $lines[1]);
        $this->assertStringStartsWith("\"'=HYPERLINK", $lines[2]);      // spreadsheet formula neutralised
        $this->assertSame('Total,5,1234.75,,', $lines[3]);
    }

    public function test_excel_file_is_a_valid_workbook_with_real_numbers_dates_and_escaped_text(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xl');
        (new XlsxWriter)->toFile($this->sample(), $path, 'Tester');

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'docProps/core.xml'] as $part) {
            $xml = $zip->getFromName($part);
            $this->assertNotFalse($xml, "$part exists");
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml), "$part is well-formed XML");
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('বাংলা নাম &amp; &lt;b&gt;tag&lt;/b&gt;', $sheet);
        $this->assertStringContainsString('t="inlineStr"', $sheet);
        $this->assertStringContainsString('<v>1234.5</v>', $sheet);
        $this->assertStringContainsString('<v>46086</v>', $sheet);           // 2026-03-05 as an Excel serial date
        $this->assertStringContainsString('<v>1234.75</v>', $sheet);         // totals row
        $this->assertStringContainsString('<autoFilter', $sheet);
        $this->assertStringContainsString('state="frozen"', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);                 // no formulas, ever
        $this->assertStringContainsString('Sample report', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        @unlink($path);
    }

    public function test_pdf_is_built_from_the_same_result_with_orientation_and_footer(): void
    {
        $w = $this->world();
        $this->inBranch($w['branch'], function () {
            $bytes = app(ExportService::class)->pdfBytes($this->sample(), null);
            $this->assertSame('%PDF-1.4 recorded', $bytes);
        });
        $call = end($this->pdf->rendered);
        $this->assertSame('P', $call['options']['orientation']);            // 5 columns → portrait
        $this->assertStringContainsString('Sample report', $call['html']);
        $this->assertStringContainsString('বাংলা নাম &amp; &lt;b&gt;tag&lt;/b&gt;', $call['html']);
        $this->assertStringContainsString('1,234.50', $call['html']);
        $this->assertStringContainsString('05/03/2026', $call['html']);
        $this->assertStringContainsString('12.5%', $call['html']);
        $this->assertStringContainsString('{PAGENO}', $call['options']['footer']);
        $this->assertStringContainsString('Reports Branch', $call['html']);

        $wide = new ReportResult('Wide', array_map(fn ($i) => ReportResult::col("c$i", "Col $i"), range(1, 9)), []);
        $this->inBranch($w['branch'], fn () => app(ExportService::class)->pdfBytes($wide));
        $this->assertSame('L', end($this->pdf->rendered)['options']['orientation']);
    }

    public function test_display_and_raw_formats(): void
    {
        $this->assertSame('1,234.50', ReportResult::display(1234.5, 'money'));
        $this->assertSame('1,235', ReportResult::display(1234.5, 'int'));
        $this->assertSame('12.5%', ReportResult::display(12.5, 'percent'));
        $this->assertSame('100%', ReportResult::display(100, 'percent'));
        $this->assertSame('05/03/2026', ReportResult::display('2026-03-05', 'date'));
        $this->assertSame('05/03/2026 14:30', ReportResult::display('2026-03-05 14:30:00', 'datetime'));
        $this->assertSame('', ReportResult::display(null, 'money'));
        $this->assertSame('2026-03-05', ReportResult::raw('2026-03-05 14:30:00', 'date'));
        $this->assertNull(ReportResult::raw('', 'int'));
    }

    // ------------------------------------------------------------------ screens, permissions, downloads

    public function test_centre_lists_only_the_reports_the_role_may_open(): void
    {
        $w = $this->world();

        $this->as($w)->get($this->url($w))->assertOk()->assertSee('Books issued')->assertSee('Fee collection')->assertSee('Asset register');
        $this->as($w, $w['librarian'])->get($this->url($w))->assertOk()->assertSee('Books issued')->assertDontSee('Fee collection')->assertDontSee('Asset register');
        $this->as($w, $w['librarian'])->get($this->url($w, 'r/finance.collection'))->assertNotFound();       // unknown key
        $this->as($w, $w['librarian'])->get($this->url($w, 'r/fees.collection'))->assertNotFound();          // exists but not for this role
        $this->as($w, $w['librarian'])->get($this->url($w, 'r/library.overdue'))->assertOk()->assertSee('Padma Nadir Majhi');
        $this->as($w)->get($this->url($w, 'r/branches.assets'))->assertNotFound();                          // central reports are not in the branch area
    }

    public function test_report_page_shows_filters_rows_totals_and_the_screen_limit(): void
    {
        $w = $this->world();
        $this->as($w)->get($this->url($w, 'r/library.fines?from=2026-03-01&to=2026-03-31'))->assertOk()
            ->assertSee('Library fines')->assertSee('Anika Rahman')->assertSee('70.00')->assertSee('Export');

        config(['reports.screen_rows' => 1]);
        $this->as($w)->get($this->url($w, 'r/students.list'))->assertOk()->assertSee('Showing the first 1')->assertSee('Anika Rahman')->assertDontSee('Babul Hasan');
    }

    public function test_download_in_every_format_needs_the_export_permission(): void
    {
        $w = $this->world();

        $xlsx = $this->as($w)->get($this->url($w, 'r/students.list/export?format=xlsx'))->assertOk();
        $this->assertStringContainsString('students-list-2026-03-15.xlsx', (string) $xlsx->headers->get('content-disposition'));
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('content-type'));
        $this->assertSame('PK', substr(file_get_contents($xlsx->baseResponse->getFile()->getPathname()), 0, 2));

        $csv = $this->as($w)->get($this->url($w, 'r/students.list/export?format=csv&q=Babul'))->assertOk();
        $body = file_get_contents($csv->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Babul Hasan', $body);
        $this->assertStringNotContainsString('Anika Rahman', $body);

        $pdf = $this->as($w)->get($this->url($w, 'r/students.list/export?format=pdf'))->assertOk();
        $this->assertSame('%PDF-1.4 recorded', file_get_contents($pdf->baseResponse->getFile()->getPathname()));

        $this->as($w)->get($this->url($w, 'r/students.list/export?format=docx'))->assertSessionHasErrors('format');
        // the librarian may look at library reports but has no reports.export
        $this->as($w, $w['librarian'])->get($this->url($w, 'r/library.overdue/export?format=csv'))->assertForbidden();
        $this->as($w, $w['librarian'])->post($this->url($w, 'r/library.overdue/queue'), ['format' => 'csv'])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'report.exported']);
    }

    // ------------------------------------------------------------------ background exports

    public function test_background_export_builds_a_private_file_and_tells_the_user(): void
    {
        $w = $this->world();
        $this->as($w)->post($this->url($w, 'r/students.list/queue'), ['format' => 'xlsx', 'status' => 'active'])->assertRedirect($this->url($w, 'exports'));

        // the queue is "sync" in tests, so the job has already run
        $run = ExportRun::query()->firstOrFail();
        $this->assertSame('done', $run->status, (string) $run->error);
        $this->assertSame($w['admin']->id, $run->user_id);
        $this->assertSame($w['branch']->id, $run->branch_id);
        $this->assertSame(2, $run->row_count);
        $this->assertSame('students-list-2026-03-15.xlsx', $run->file_name);
        $this->assertTrue(Storage::disk('local')->exists($run->file_path));
        $this->assertSame('PK', substr(Storage::disk('local')->get($run->file_path), 0, 2));
        $this->assertTrue($run->expires_at->isSameDay(now()->addDays(7)));

        // an in-app message with the link was queued for the user
        $this->assertDatabaseHas('notification_messages', ['event' => 'reports.export_ready']);

        $this->as($w)->get($this->url($w, 'exports'))->assertOk()->assertSee('Student list')->assertSee('Ready');
        $download = $this->as($w)->get($this->url($w, "exports/{$run->id}/download"))->assertOk();
        $this->assertStringContainsString('students-list-2026-03-15.xlsx', (string) $download->headers->get('content-disposition'));
        $this->assertSame(1, $run->fresh()->downloads);
    }

    public function test_exports_belong_to_their_owner_and_their_branch(): void
    {
        $w = $this->world();
        $this->as($w)->post($this->url($w, 'r/students.list/queue'), ['format' => 'csv']);
        $run = ExportRun::query()->firstOrFail();

        $colleague = $this->branchUser($w['branch'], 'branch-admin');
        $this->as($w, $colleague)->get($this->url($w, "exports/{$run->id}/download"))->assertNotFound();
        $this->as($w, $colleague)->delete($this->url($w, "exports/{$run->id}"))->assertNotFound();
        $this->as($w, $colleague)->get($this->url($w, 'exports'))->assertOk()->assertSee('No exports yet');

        $other = $this->makeBranch($w['branch']->institution, ['name' => 'Other']);
        $stranger = $this->branchUser($other, 'branch-admin');
        $this->actingOnBranch($stranger, $other)->get($this->branchUrl($other, "reports/exports/{$run->id}/download"))->assertNotFound();

        // the owner can delete it: the file goes too
        $path = $run->file_path;
        $this->as($w)->delete($this->url($w, "exports/{$run->id}"))->assertRedirect();
        $this->assertDatabaseMissing('export_runs', ['id' => $run->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_long_reports_are_prepared_in_the_background_instead_of_downloaded(): void
    {
        $w = $this->world();
        config(['reports.inline_max_rows' => 1]);

        $this->as($w)->get($this->url($w, 'r/students.list/export?format=csv'))->assertRedirect($this->url($w, 'exports'));
        $this->assertSame(1, ExportRun::query()->where('status', 'done')->count());

        config(['reports.max_rows' => 1]);
        $this->as($w)->get($this->url($w, 'r/students.list'))->assertSessionHasErrors('report');   // too many rows even for the screen
    }

    public function test_a_failing_export_is_recorded_not_lost_and_pending_exports_are_limited(): void
    {
        $w = $this->world();
        // the report disappears from the role between asking and running
        $run = ExportRun::query()->create(['institution_id' => $w['branch']->institution_id, 'branch_id' => $w['branch']->id, 'user_id' => $w['librarian']->id, 'report_key' => 'library.overdue',
            'title' => 'Overdue books', 'format' => 'csv', 'filters' => [], 'status' => 'queued']);
        $this->inBranch($w['branch'], fn () => app(ExportService::class)->execute($run));
        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->error);
        $this->assertDatabaseHas('notification_messages', ['event' => 'reports.export_failed']);

        // at most three exports waiting / running per user
        foreach (range(1, 3) as $i) {
            ExportRun::query()->create(['institution_id' => $w['branch']->institution_id, 'branch_id' => $w['branch']->id, 'user_id' => $w['admin']->id, 'report_key' => 'students.list', 'title' => 'x', 'format' => 'csv', 'status' => 'queued']);
        }
        $this->as($w)->post($this->url($w, 'r/students.list/queue'), ['format' => 'csv'])->assertSessionHasErrors('report');
    }

    public function test_housekeeping_deletes_expired_files_and_fails_stuck_runs(): void
    {
        $w = $this->world();
        $this->as($w)->post($this->url($w, 'r/students.list/queue'), ['format' => 'csv']);
        $run = ExportRun::query()->firstOrFail();
        $path = $run->file_path;
        $stuck = ExportRun::query()->create(['institution_id' => $w['branch']->institution_id, 'branch_id' => $w['branch']->id, 'user_id' => $w['admin']->id, 'report_key' => 'students.list',
            'title' => 'Stuck', 'format' => 'csv', 'status' => 'running', 'created_at' => now()->subHours(7)]);

        $this->artisan('reports:housekeeping')->assertSuccessful();
        $this->assertSame('done', $run->fresh()->status);               // still inside its 7 days
        $this->assertSame('failed', $stuck->fresh()->status);

        $this->travelTo(now()->addDays(8));
        $this->artisan('reports:housekeeping')->assertSuccessful();
        $this->assertSame('expired', $run->fresh()->status);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->as($w)->get($this->url($w, "exports/{$run->id}/download"))->assertNotFound();
    }

    // ------------------------------------------------------------------ central

    public function test_central_area_has_branch_comparison_reports_only(): void
    {
        $admin = User::factory()->role('institution-admin')->create();
        $north = $this->makeBranch($admin->institution, ['name' => 'North Campus']);
        $this->inBranch($north, fn () => app(ChartOfAccounts::class)->ensure());
        $c = fn (string $p = '') => $this->centralUrl('super-admin/reports'.($p === '' ? '' : '/'.$p));

        $this->actingCentral($admin)->get($c())->assertOk()->assertSee('Branch-wise assets')->assertSee('Branch-wise inventory')->assertSee('Branch-wise finance')->assertDontSee('Books issued');
        $this->actingCentral($admin)->get($c('r/branches.assets'))->assertOk()->assertSee('North Campus');
        $this->actingCentral($admin)->get($c('r/library.issued'))->assertNotFound();

        $this->actingCentral($admin)->get($c('r/branches.finance/export?format=xlsx'))->assertOk();
        $this->actingCentral($admin)->post($c('r/branches.inventory/queue'), ['format' => 'csv'])->assertRedirect($c('exports'));
        $run = ExportRun::query()->firstOrFail();
        $this->assertNull($run->branch_id);
        $this->assertSame('done', $run->status, (string) $run->error);
        $this->actingCentral($admin)->get($c("exports/{$run->id}/download"))->assertOk();
        $this->actingCentral($admin)->get($c('exports'))->assertOk()->assertSee('Branch-wise inventory');
    }

    public function test_registry_is_consistent(): void
    {
        $registry = app(ReportRegistry::class);
        $seen = [];
        foreach ($registry->all() as $key => $report) {
            $this->assertSame($key, $report->key());
            $this->assertMatchesRegularExpression('/^[a-z0-9.\-]+$/', $key, 'keys are URL safe');
            $this->assertArrayNotHasKey($key, $seen);
            $seen[$key] = true;
            $this->assertContains($report->group(), array_keys(config('reports.groups')), "$key group is listed");
            $this->assertContains($report->scope(), ['branch', 'central']);
            $this->assertNotSame('', $report->title());
            foreach ($report->filters() as $spec) {
                $this->assertContains($spec['type'], ['date', 'month', 'select', 'text', 'bool', 'number'], "$key filter type");
                $this->assertArrayHasKey('label', $spec);
            }
        }
    }
}
