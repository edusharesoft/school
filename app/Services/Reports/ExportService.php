<?php

namespace App\Services\Reports;

use App\Jobs\RunReportExport;
use App\Models\Reports\ExportRun;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use App\Services\Reports\Exporters\CsvExporter;
use App\Services\Reports\Exporters\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Runs a report and turns the result into a CSV / Excel / PDF file — either straight away (small reports) or in the
 * background (an ExportRun row + RunReportExport job; the finished file waits on the private disk for a few days).
 */
class ExportService
{
    public function __construct(
        protected ReportRegistry $registry,
        protected CsvExporter $csv,
        protected XlsxWriter $xlsx,
        protected PdfService $pdf,
        protected DocumentService $documents,
        protected NotificationHub $hub,
        protected RecipientResolver $recipients,
    ) {}

    /** Run a report with clean filters and add what the exports print above the table. */
    public function build(Report $report, array $input, ?User $user = null): ReportResult
    {
        $filters = $report->normalize($input);
        $result = $report->run($filters);
        $result->title = $result->title ?: $report->title();
        $result->filters = $result->filters ?: $report->filterSummary($filters);

        $limit = (int) config('reports.max_rows', 100000);
        if ($result->rowCount() > $limit) {
            throw ValidationException::withMessages(['report' => __('This report has more than :n rows. Narrow the filters and try again.', ['n' => number_format($limit)])]);
        }

        return $result;
    }

    /** Write the result to $path in the given format. */
    public function write(ReportResult $result, string $format, string $path, ?User $user = null): void
    {
        match ($format) {
            'csv' => $this->csv->toFile($result, $path),
            'xlsx' => $this->xlsx->toFile($result, $path, $user?->name),
            'pdf' => File::put($path, $this->pdfBytes($result, $user)),
            default => throw ValidationException::withMessages(['format' => __('Unknown export format.')]),
        };
    }

    public function pdfBytes(ReportResult $result, ?User $user = null): string
    {
        $central = tenant()->isCentral();
        $brand = $central ? [] : $this->documents->branding();
        $html = view('pdf.report', [
            'result' => $result,
            'accent' => $brand['accent'] ?? '#1f6f5c',
            'logo' => $brand['logo'] ?? null,
            'institution' => tenant()->institution()?->name ?? config('app.name'),
            'branchName' => $central ? __('All branches') : tenant()->branch()?->displayName(),
            'by' => $user?->name,
        ])->render();

        return $this->pdf->render($html, [
            'orientation' => count($result->columns) > 6 ? 'L' : 'P',
            'margins' => [10, 10, 14, 10],
            'title' => $result->title,
            'footer' => '<div style="text-align:center; font-size:8pt; color:#64748b">'.e($result->title).' — {PAGENO} / {nbpg}</div>',
        ]);
    }

    public function filename(Report $report, string $format): string
    {
        return Str::slug(str_replace('.', '-', $report->key())).'-'.now()->format('Y-m-d').'.'.$format;
    }

    public function mime(string $format): string
    {
        return (new ExportRun(['format' => $format]))->mime();
    }

    /** Render to a temporary file and return its path (caller deletes it). */
    public function tempFile(ReportResult $result, string $format, ?User $user = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $this->write($result, $format, $path, $user);

        return $path;
    }

    // ───────────────────────────── background ─────────────────────────────

    public function queue(Report $report, array $input, string $format, User $user): ExportRun
    {
        if (! in_array($format, ExportRun::FORMATS, true)) {
            throw ValidationException::withMessages(['format' => __('Unknown export format.')]);
        }
        if (ExportRun::query()->ownedBy($user)->pending()->count() >= (int) config('reports.max_pending_per_user', 3)) {
            throw ValidationException::withMessages(['report' => __('You already have exports being prepared. Wait for them to finish.')]);
        }

        $run = ExportRun::query()->create([
            'institution_id' => tenant()->institutionId(), 'branch_id' => tenant()->isCentral() ? null : tenant()->branchId(), 'user_id' => $user->id,
            'report_key' => $report->key(), 'title' => $report->title(), 'format' => $format,
            'filters' => $report->normalize($input), 'status' => 'queued',
        ]);
        RunReportExport::dispatch($run->id);

        return $run;
    }

    /** Build the file of a queued run (called by the job inside the right tenant context). */
    public function execute(ExportRun $run): void
    {
        if ($run->status === 'done' || $run->status === 'expired') {
            return;
        }
        $run->forceFill(['status' => 'running', 'started_at' => now(), 'error' => null])->save();
        $user = $run->user;

        try {
            $report = $this->registry->find($run->report_key);
            if (! $report || ! $user || ! $report->canExport($user)) {
                throw new \RuntimeException(__('This report is not available to you any more.'));
            }
            $result = $this->build($report, (array) $run->filters, $user);

            $tmp = $this->tempFile($result, $run->format, $user);
            $disk = Storage::disk((string) config('reports.disk', 'local'));
            $path = trim((string) config('reports.folder', 'exports'), '/').'/'.$run->id.'-'.Str::random(12).'.'.$run->format;
            $disk->put($path, fopen($tmp, 'rb'));
            $bytes = filesize($tmp);
            @unlink($tmp);

            $run->forceFill([
                'status' => 'done', 'file_path' => $path, 'file_name' => $this->filename($report, $run->format), 'bytes' => $bytes,
                'row_count' => $result->rowCount(), 'finished_at' => now(), 'expires_at' => now()->addDays((int) config('reports.keep_days', 7)),
            ])->save();
            $this->notify($run, 'reports.export_ready');
        } catch (\Throwable $e) {
            report($e);
            $run->forceFill(['status' => 'failed', 'error' => mb_substr($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage(), 0, 480), 'finished_at' => now()])->save();
            $this->notify($run, 'reports.export_failed');
        }
    }

    /** Delete the files of exports past their date and mark them expired; also fail runs stuck for hours. @return int files removed */
    public function purge(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $disk = Storage::disk((string) config('reports.disk', 'local'));
        $removed = 0;

        foreach (ExportRun::query()->where('status', 'done')->where('expires_at', '<=', $now)->get() as $run) {
            if ($run->file_path && $disk->exists($run->file_path)) {
                $disk->delete($run->file_path);
                $removed++;
            }
            $run->forceFill(['status' => 'expired', 'file_path' => null])->save();
        }
        ExportRun::query()->whereIn('status', ['queued', 'running'])->where('created_at', '<=', $now->subHours(6))
            ->update(['status' => 'failed', 'error' => 'Timed out — the queue worker may not be running.', 'finished_at' => $now]);
        ExportRun::query()->whereIn('status', ['expired', 'failed'])->where('created_at', '<=', $now->subDays(60))->delete();

        return $removed;
    }

    public function deleteFile(ExportRun $run): void
    {
        if ($run->file_path) {
            Storage::disk((string) config('reports.disk', 'local'))->delete($run->file_path);
        }
    }

    protected function notify(ExportRun $run, string $event): void
    {
        try {
            if (! $run->user || tenant()->isCentral()) {
                return;     // in-app messages are stored per branch; central users see the finished list on the Exports page
            }
            $vars = ['name' => $run->user->name, 'report' => $run->title, 'format' => strtoupper($run->format), 'rows' => (string) ($run->row_count ?? 0),
                'error' => (string) $run->error, 'url' => route('branch.reports.exports.index')];
            $this->hub->notify($event, [$this->recipients->user($run->user)->with($vars)], $vars, $run, ['url' => $vars['url']]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
