<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentItem;
use App\Models\Inventory\StockAudit;
use App\Models\Inventory\StockAuditItem;
use App\Models\Inventory\StockIssue;
use App\Models\Inventory\StockIssueItem;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock keeping. Quantity and value of an item change ONLY here, always together with a stock card line (StockTransaction)
 * and — for money — a journal entry, so the stock sub-ledger and the "Inventory stock" account never drift apart.
 *
 *  issue       (consumables to a department / person)   Dr consumption expense      / Cr Inventory stock
 *  adjustment  shortage                                  Dr Stock adjustment         / Cr Inventory stock
 *              surplus / recount gain                    Dr Inventory stock          / Cr Stock adjustment   (opening stock: Cr Opening balance equity)
 *  audit       counted − book stock becomes an adjustment (reason "recount")
 *
 * Cost is the moving weighted average: stock_value / quantity_on_hand. Stock going out takes its value at the average cost
 * (the last unit takes whatever value is left, so nothing is stranded). Nothing here lets stock go below zero.
 */
class InventoryService
{
    public function __construct(
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
        protected SequenceService $sequences,
        protected TenantContext $tenant,
    ) {}

    // ───────────────────────────── catalogue ─────────────────────────────

    public function createItem(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            $item = new Item($data);
            $item->forceFill(['code' => sprintf('IT-%06d', $this->sequences->next('inventory_item'))])->save();

            return $item;
        });
    }

    // ───────────────────────────── stock movement (low level) ─────────────────────────────

    /**
     * Move stock of one item. $quantity is signed. For an increase $amount is the value coming in; for a decrease it is ignored and
     * the value is taken at the average cost. Returns the stock card line (amount is signed).
     */
    public function move(Item $item, string $type, float $quantity, float $amount, \DateTimeInterface|string $date, ?Model $source = null, ?string $reference = null, ?string $note = null, ?User $user = null): StockTransaction
    {
        $quantity = round($quantity, 3);
        if ($quantity == 0.0) {
            throw ValidationException::withMessages(['quantity' => __('Quantity cannot be zero.')]);
        }

        return DB::transaction(function () use ($item, $type, $quantity, $amount, $date, $source, $reference, $note, $user) {
            /** @var Item $locked */
            $locked = Item::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($quantity > 0) {
                $value = round(max(0.0, $amount), 2);
            } else {
                $out = abs($quantity);
                if ($out > $locked->quantity_on_hand + 0.0005) {
                    throw ValidationException::withMessages(['quantity' => __('Not enough stock of :item: :have :unit on hand, :need needed.', [
                        'item' => $locked->name, 'have' => $this->num($locked->quantity_on_hand), 'unit' => $locked->unit, 'need' => $this->num($out),
                    ])]);
                }
                $value = -($out >= $locked->quantity_on_hand - 0.0005 ? $locked->stock_value : round($locked->stock_value * $out / $locked->quantity_on_hand, 2));
            }

            return $this->apply($locked, $type, $quantity, $value, $date, $source, $reference, $note, $user);
        });
    }

    /** Put back or take out an exact quantity AND value (used to reverse a document at the value it originally moved). */
    public function moveExact(Item $item, string $type, float $quantity, float $value, \DateTimeInterface|string $date, ?Model $source = null, ?string $reference = null, ?string $note = null, ?User $user = null): StockTransaction
    {
        return DB::transaction(function () use ($item, $type, $quantity, $value, $date, $source, $reference, $note, $user) {
            $locked = Item::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            if ($quantity < 0 && (abs($quantity) > $locked->quantity_on_hand + 0.0005 || abs($value) > $locked->stock_value + 0.005)) {
                throw ValidationException::withMessages(['quantity' => __(':item has already been used or issued — reverse those movements first.', ['item' => $locked->name])]);
            }

            return $this->apply($locked, $type, round($quantity, 3), round($value, 2), $date, $source, $reference, $note, $user);
        });
    }

    protected function apply(Item $item, string $type, float $quantity, float $value, \DateTimeInterface|string $date, ?Model $source, ?string $reference, ?string $note, ?User $user): StockTransaction
    {
        $newQty = round($item->quantity_on_hand + $quantity, 3);
        $newValue = round($item->stock_value + $value, 2);
        if ($newQty <= 0) {
            $newQty = max(0.0, $newQty);
        }
        $item->forceFill(['quantity_on_hand' => $newQty, 'stock_value' => max(0.0, $newValue)])->save();

        return StockTransaction::query()->create([
            'inventory_item_id' => $item->id, 'date' => CarbonImmutable::parse($date)->toDateString(), 'type' => $type,
            'quantity' => $quantity, 'amount' => $value, 'balance_quantity' => $newQty, 'balance_value' => max(0.0, $newValue),
            'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(),
            'reference' => $reference ? mb_substr($reference, 0, 60) : null, 'note' => $note ? mb_substr($note, 0, 255) : null, 'created_by' => $user?->id,
        ]);
    }

    // ───────────────────────────── issue to departments / people ─────────────────────────────

    /**
     * @param  array{date:string, department_id?:?int, employee_id?:?int, recipient?:?string, purpose?:?string}  $data
     * @param  array<int,array{inventory_item_id:int|string, quantity:float|int|string}>  $lines
     */
    public function issue(array $data, array $lines, User $user): StockIssue
    {
        $lines = $this->mergeLines($lines);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one item.')]);
        }
        if (empty($data['department_id']) && empty($data['employee_id']) && trim((string) ($data['recipient'] ?? '')) === '') {
            throw ValidationException::withMessages(['recipient' => __('Say who the goods are issued to — a department, an employee or a name.')]);
        }
        $date = CarbonImmutable::parse($data['date']);

        return DB::transaction(function () use ($data, $lines, $user, $date) {
            $items = Item::query()->with('category')->whereIn('id', $lines->keys())->get()->keyBy('id');
            $issue = new StockIssue([
                'date' => $date->toDateString(), 'department_id' => $data['department_id'] ?? null, 'employee_id' => $data['employee_id'] ?? null,
                'recipient' => $this->clip($data['recipient'] ?? null, 150), 'purpose' => $this->clip($data['purpose'] ?? null, 255),
            ]);
            $issue->forceFill([
                'issue_no' => $this->docNo('ISS', $date), 'status' => 'issued', 'issued_by' => $user->id, 'total' => 0,
            ])->save();

            $byAccount = [];
            $total = 0.0;
            foreach ($lines as $itemId => $qty) {
                $item = $items[$itemId] ?? throw ValidationException::withMessages(['lines' => __('Unknown item.')]);
                if (! $item->is_active) {
                    throw ValidationException::withMessages(['lines' => __(':item is inactive.', ['item' => $item->name])]);
                }
                if ($item->item_type !== 'consumable') {
                    throw ValidationException::withMessages(['lines' => __(':item is durable equipment — it goes to the asset register, not out as a consumable.', ['item' => $item->name])]);
                }
                $tx = $this->move($item, 'issue', -$qty, 0, $date, $issue, $issue->issue_no, $issue->purpose, $user);
                $value = abs($tx->amount);
                StockIssueItem::query()->create(['stock_issue_id' => $issue->id, 'inventory_item_id' => $item->id, 'quantity' => $qty, 'amount' => $value]);
                $account = $item->category?->expense_account_id ?: $this->chart->id('stock_consumed');
                $byAccount[$account] = ($byAccount[$account] ?? 0.0) + $value;
                $total += $value;
            }

            $total = round($total, 2);
            if ($total > 0) {
                $post = [['account_id' => $this->chart->id('inventory_stock'), 'credit' => $total, 'description' => $issue->issue_no]];
                foreach ($byAccount as $accountId => $value) {
                    $post[] = ['account_id' => (int) $accountId, 'debit' => round($value, 2), 'description' => $issue->recipientLabel()];
                }
                $this->ledger->post('journal', $date, __('Stock issue :no — :text', ['no' => $issue->issue_no, 'text' => $issue->recipientLabel()]), $post, $issue, $user);
            }
            $issue->forceFill(['total' => $total])->save();

            return $issue;
        });
    }

    public function reverseIssue(StockIssue $issue, string $reason, User $user): StockIssue
    {
        if ($issue->status !== 'issued') {
            throw ValidationException::withMessages(['status' => __('This issue has already been reversed.')]);
        }

        return DB::transaction(function () use ($issue, $reason, $user) {
            foreach ($issue->items()->get() as $line) {
                $this->moveExact($line->item, 'reversal', $line->quantity, $line->amount, now(), $issue, $issue->issue_no, __('Returned — :reason', ['reason' => $reason]), $user);
            }
            $this->reverseEntries($issue, $reason, $user);
            $issue->forceFill(['status' => 'reversed'])->save();

            return $issue;
        });
    }

    // ───────────────────────────── adjustments ─────────────────────────────

    /**
     * @param  array{date:string, reason:string, note?:?string}  $data
     * @param  array<int,array{inventory_item_id:int|string, quantity:float|int|string, unit_cost?:float|int|string|null}>  $lines  signed quantity; unit_cost only matters for an increase
     */
    public function adjust(array $data, array $lines, User $user, ?StockAudit $audit = null): StockAdjustment
    {
        if (! in_array($data['reason'], StockAdjustment::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('Choose a reason.')]);
        }
        $clean = collect($lines)->filter(fn ($l) => ! empty($l['inventory_item_id']) && round((float) ($l['quantity'] ?? 0), 3) != 0.0)->values();
        if ($clean->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one item with a quantity.')]);
        }
        if ($clean->pluck('inventory_item_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => __('An item can appear only once.')]);
        }
        $date = CarbonImmutable::parse($data['date']);

        return DB::transaction(function () use ($data, $clean, $user, $audit, $date) {
            $items = Item::query()->whereIn('id', $clean->pluck('inventory_item_id'))->get()->keyBy('id');
            $adjustment = new StockAdjustment(['date' => $date->toDateString(), 'reason' => $data['reason'], 'note' => $this->clip($data['note'] ?? null, 255)]);
            $adjustment->forceFill([
                'adjustment_no' => $this->docNo('ADJ', $date), 'status' => 'posted', 'net_value' => 0, 'stock_audit_id' => $audit?->id,
                'created_by' => $user->id, 'approved_by' => $user->id,
            ])->save();

            $gain = 0.0;
            $loss = 0.0;
            foreach ($clean as $line) {
                $item = $items[(int) $line['inventory_item_id']] ?? throw ValidationException::withMessages(['lines' => __('Unknown item.')]);
                $qty = round((float) $line['quantity'], 3);
                $in = $qty > 0 ? round($qty * (isset($line['unit_cost']) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null ? (float) $line['unit_cost'] : $item->averageCost()), 2) : 0.0;
                $tx = $this->move($item, 'adjustment', $qty, $in, $date, $adjustment, $adjustment->adjustment_no, $data['reason'], $user);
                StockAdjustmentItem::query()->create(['stock_adjustment_id' => $adjustment->id, 'inventory_item_id' => $item->id, 'quantity' => $qty, 'amount' => $tx->amount]);
                $tx->amount > 0 ? $gain += $tx->amount : $loss += abs($tx->amount);
            }

            $gain = round($gain, 2);
            $loss = round($loss, 2);
            $lines = [];
            if ($gain > 0) {
                $lines[] = ['account_id' => $this->chart->id('inventory_stock'), 'debit' => $gain];
                $lines[] = ['account_id' => $this->chart->id($data['reason'] === 'opening' ? 'opening_balance' : 'stock_adjustment'), 'credit' => $gain];
            }
            if ($loss > 0) {
                $lines[] = ['account_id' => $this->chart->id('stock_adjustment'), 'debit' => $loss];
                $lines[] = ['account_id' => $this->chart->id('inventory_stock'), 'credit' => $loss];
            }
            if ($lines) {
                $this->ledger->post('journal', $date, __('Stock adjustment :no — :reason', ['no' => $adjustment->adjustment_no, 'reason' => __(ucfirst($data['reason']))]), $lines, $adjustment, $user);
            }
            $adjustment->forceFill(['net_value' => round($gain - $loss, 2)])->save();

            return $adjustment;
        });
    }

    public function reverseAdjustment(StockAdjustment $adjustment, string $reason, User $user): StockAdjustment
    {
        if ($adjustment->status !== 'posted') {
            throw ValidationException::withMessages(['status' => __('This adjustment has already been reversed.')]);
        }

        return DB::transaction(function () use ($adjustment, $reason, $user) {
            foreach ($adjustment->items()->get() as $line) {
                $this->moveExact($line->item, 'reversal', -$line->quantity, -$line->amount, now(), $adjustment, $adjustment->adjustment_no, __('Reversal — :reason', ['reason' => $reason]), $user);
            }
            $this->reverseEntries($adjustment, $reason, $user);
            $adjustment->forceFill(['status' => 'reversed'])->save();

            return $adjustment;
        });
    }

    // ───────────────────────────── audit (physical count) ─────────────────────────────

    /** Open a count sheet with the book stock of every active item (of one category, or all). */
    public function startAudit(array $data, User $user): StockAudit
    {
        $date = CarbonImmutable::parse($data['date']);

        return DB::transaction(function () use ($data, $user, $date) {
            $items = Item::query()->where('is_active', true)
                ->when(! empty($data['inventory_category_id']), fn ($q) => $q->where('inventory_category_id', $data['inventory_category_id']))
                ->orderBy('name')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['inventory_category_id' => __('There is no active item to count.')]);
            }
            $audit = new StockAudit(['date' => $date->toDateString(), 'title' => $data['title'], 'inventory_category_id' => $data['inventory_category_id'] ?? null, 'note' => $this->clip($data['note'] ?? null, 255)]);
            $audit->forceFill(['audit_no' => $this->docNo('AUD', $date), 'status' => 'open', 'started_by' => $user->id])->save();
            foreach ($items as $item) {
                StockAuditItem::query()->create(['stock_audit_id' => $audit->id, 'inventory_item_id' => $item->id, 'expected_quantity' => $item->quantity_on_hand]);
            }

            return $audit;
        });
    }

    /** @param array<int,array{counted:mixed, note?:?string}> $counts keyed by audit item id */
    public function saveCounts(StockAudit $audit, array $counts): int
    {
        $this->assertOpen($audit);
        $saved = 0;
        foreach ($audit->items()->get() as $line) {
            if (! isset($counts[$line->id])) {
                continue;
            }
            $value = $counts[$line->id]['counted'] ?? null;
            $line->counted_quantity = ($value === null || $value === '') ? null : round(max(0.0, (float) $value), 3);
            $line->note = $this->clip($counts[$line->id]['note'] ?? null, 255);
            if ($line->isDirty()) {
                $line->save();
                $saved++;
            }
        }

        return $saved;
    }

    /** Post the differences of every counted line as one "recount" adjustment (counted − stock on hand now) and close the audit. */
    public function completeAudit(StockAudit $audit, User $user): StockAudit
    {
        $this->assertOpen($audit);

        return DB::transaction(function () use ($audit, $user) {
            $counted = $audit->items()->with('item')->whereNotNull('counted_quantity')->get();
            if ($counted->isEmpty()) {
                throw ValidationException::withMessages(['counts' => __('Enter at least one counted quantity before completing the audit.')]);
            }
            $lines = $counted->map(fn (StockAuditItem $l) => [
                'inventory_item_id' => $l->inventory_item_id, 'quantity' => round($l->counted_quantity - Item::query()->whereKey($l->inventory_item_id)->value('quantity_on_hand'), 3),
            ])->filter(fn ($l) => $l['quantity'] != 0.0)->values()->all();

            if ($lines) {
                $this->adjust(['date' => now()->toDateString(), 'reason' => 'recount', 'note' => __('Stock audit :no', ['no' => $audit->audit_no])], $lines, $user, $audit);
            }
            $audit->forceFill(['status' => 'completed', 'completed_by' => $user->id, 'completed_at' => now()])->save();

            return $audit;
        });
    }

    public function cancelAudit(StockAudit $audit): StockAudit
    {
        $this->assertOpen($audit);
        $audit->forceFill(['status' => 'cancelled'])->save();

        return $audit;
    }

    protected function assertOpen(StockAudit $audit): void
    {
        if ($audit->status !== 'open') {
            throw ValidationException::withMessages(['status' => __('This audit is already closed.')]);
        }
    }

    // ───────────────────────────── helpers ─────────────────────────────

    /** Posted, un-reversed journal entries of a document are reversed with their mirror entries. */
    public function reverseEntries(Model $source, string $reason, User $user): void
    {
        foreach ($this->ledger->entriesFor($source)->filter(fn ($e) => $e->status === 'posted' && ! $e->reversal_of_id) as $entry) {
            $this->ledger->reverse($entry, $reason, $user);
        }
    }

    /** Document number: BRANCH-PREFIXyy-00001, a per-branch yearly sequence. */
    public function docNo(string $prefix, \DateTimeInterface|string $date): string
    {
        $date = CarbonImmutable::parse($date);

        return sprintf('%s-%s%s-%05d', strtoupper($this->tenant->branch()->code), $prefix, $date->format('y'), $this->sequences->next('inventory_'.strtolower($prefix), $date->format('Y')));
    }

    /** @return Collection<int,float> item id => total quantity (blank and zero lines dropped, duplicates added up) */
    public function mergeLines(array $lines): Collection
    {
        return collect($lines)->filter(fn ($l) => ! empty($l['inventory_item_id']) && (float) ($l['quantity'] ?? 0) > 0)
            ->groupBy(fn ($l) => (int) $l['inventory_item_id'])->map(fn ($g) => round($g->sum(fn ($l) => (float) $l['quantity']), 3));
    }

    protected function clip(?string $text, int $max): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    protected function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
