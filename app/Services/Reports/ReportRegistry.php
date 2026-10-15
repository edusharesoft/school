<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Collection;

/** All reports of the centre (config/reports.php), looked up by key and filtered by what the user may see. */
class ReportRegistry
{
    /** @var Collection<string,Report>|null */
    protected ?Collection $reports = null;

    /** @return Collection<string,Report> */
    public function all(): Collection
    {
        return $this->reports ??= collect((array) config('reports.reports', []))
            ->map(fn (string $class) => app($class))
            ->keyBy(fn (Report $r) => $r->key());
    }

    public function find(string $key): ?Report
    {
        return $this->all()->get($key);
    }

    /** Reports of one scope the user may open, grouped by their group key (in config order). @return Collection<string,Collection<int,Report>> */
    public function grouped(?User $user, string $scope = 'branch'): Collection
    {
        $order = array_keys((array) config('reports.groups', []));

        return $this->all()
            ->filter(fn (Report $r) => $r->scope() === $scope && $r->canView($user))
            ->groupBy(fn (Report $r) => $r->group())
            ->sortBy(fn ($reports, $group) => ($i = array_search($group, $order, true)) === false ? 999 : $i)
            ->map(fn (Collection $reports) => $reports->values());
    }

    public function groupLabel(string $group): string
    {
        return (string) __(config("reports.groups.$group", ucfirst($group)));
    }
}
