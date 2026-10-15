<?php

namespace App\Services\Reports\Definitions\Transport;

use App\Models\Transport\Driver;
use App\Models\Transport\Vehicle;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Insurance, fitness, tax token and route permit of every vehicle, plus the licences of drivers — what has expired or is about to. */
class VehicleComplianceReport extends Report
{
    public function key(): string { return 'transport.compliance'; }

    public function title(): string { return __('Vehicle and driver documents'); }

    public function description(): string { return __('Expiry dates of vehicle papers and driver licences; shows what is expired or expiring soon.'); }

    public function permission(): string { return 'transport.view'; }

    public function filters(): array
    {
        return [['name' => 'days', 'type' => 'select', 'label' => 'Warn within', 'options' => [15 => __('15 days'), 30 => __('30 days'), 60 => __('60 days'), 90 => __('90 days')], 'default' => 30],
            ['name' => 'attention', 'type' => 'bool', 'label' => 'Only items needing attention']];
    }

    public function run(array $filters): R
    {
        $today = CarbonImmutable::today();
        $days = (int) ($filters['days'] ?? 30) ?: 30;
        $rows = [];

        foreach (Vehicle::query()->where('status', '!=', 'retired')->orderBy('registration_no')->get() as $v) {
            foreach (Vehicle::DOCUMENTS as $field => $label) {
                if ($v->{$field}) {
                    $rows[] = $this->row(__('Vehicle'), $v->registration_no.($v->name ? ' ('.$v->name.')' : ''), __($label), $v->{$field}, $today, $days);
                }
            }
        }
        foreach (Driver::query()->where('is_active', true)->whereNotNull('license_expiry')->orderBy('name')->get() as $d) {
            $rows[] = $this->row(__($d->role === 'helper' ? 'Helper' : 'Driver'), $d->name.($d->phone ? ' · '.$d->phone : ''), __('Driving licence').($d->license_no ? ' '.$d->license_no : ''), $d->license_expiry, $today, $days);
        }

        usort($rows, fn ($a, $b) => strcmp((string) $a['expiry'], (string) $b['expiry']));
        if (! empty($filters['attention'])) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['state'] !== __('Valid')));
        }

        $r = $this->result([R::col('kind', __('Type')), R::col('item', __('Vehicle / person')), R::col('document', __('Document')), R::col('expiry', __('Expires'), 'date'),
            R::col('left', __('Days left'), 'int'), R::col('state', __('Status'))], $rows);
        $r->subtitle = __('As of :date', ['date' => $today->format('d/m/Y')]);

        return $r;
    }

    protected function row(string $kind, string $item, string $document, $expiry, CarbonImmutable $today, int $days): array
    {
        $left = (int) $today->diffInDays(CarbonImmutable::parse($expiry)->startOfDay(), false);

        return ['kind' => $kind, 'item' => $item, 'document' => $document, 'expiry' => CarbonImmutable::parse($expiry)->toDateString(), 'left' => $left,
            'state' => $left < 0 ? __('Expired') : ($left <= $days ? __('Expiring soon') : __('Valid'))];
    }
}
