<?php

namespace App\Services\Payments\BanglaQr;

use InvalidArgumentException;

/**
 * Reads a bank / mobile-wallet statement exported as CSV. Column names differ from bank to bank, so the header row is matched by
 * name: a transaction-ID column and an amount column are required, a reference / narration column and a date column are used when
 * present. Returns rows of ['trx' => …, 'amount' => …, 'ref' => …, 'date' => …].
 */
class StatementParser
{
    public const MAX_ROWS = 3000;

    private const TRX = ['trxid', 'transactionid', 'txnid', 'trxno', 'transactionno', 'txnno', 'transactionreference', 'trxreference', 'transactionnumber'];

    private const AMOUNT = ['amount', 'credit', 'creditamount', 'received', 'receivedamount', 'paidamount', 'deposit', 'cr'];

    private const REF = ['reference', 'billno', 'billnumber', 'ref', 'refno', 'narration', 'remarks', 'description', 'particulars', 'details', 'note', 'billreference'];

    private const DATE = ['date', 'transactiondate', 'txndate', 'valuedate', 'datetime', 'time'];

    /** @return list<array{trx:string,amount:float,ref:string,date:?string}> */
    public static function parse(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new InvalidArgumentException(__('The file could not be read.'));
        }
        $first = (string) fgets($handle);
        rewind($handle);
        $delimiter = collect([',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")])->sortDesc()->keys()->first();

        $header = null;
        $cols = [];
        $rows = [];
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line = array_map(fn ($v) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), $line);
            if ($line === [] || count(array_filter($line, fn ($v) => $v !== '')) === 0) {
                continue;
            }
            if ($header === null) {
                $header = array_map(fn ($h) => preg_replace('/[^a-z0-9]/', '', strtolower($h)) ?? '', $line);
                $cols = [
                    'trx' => self::find($header, self::TRX), 'amount' => self::find($header, self::AMOUNT),
                    'ref' => self::find($header, self::REF), 'date' => self::find($header, self::DATE),
                ];
                if ($cols['trx'] === null || $cols['amount'] === null) {
                    fclose($handle);
                    throw new InvalidArgumentException(__('The first row of the statement must name a transaction ID column (for example "TrxID") and an amount column (for example "Amount" or "Credit").'));
                }

                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                throw new InvalidArgumentException(__('The statement has more than :max rows. Upload it in parts.', ['max' => self::MAX_ROWS]));
            }
            $amount = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '', (string) ($line[$cols['amount']] ?? '')));
            $trx = (string) ($line[$cols['trx']] ?? '');
            if ($trx === '' || $amount <= 0) {
                continue;
            }
            $rows[] = ['trx' => $trx, 'amount' => round($amount, 2), 'ref' => (string) ($cols['ref'] !== null ? ($line[$cols['ref']] ?? '') : implode(' ', $line)), 'date' => $cols['date'] !== null ? (($line[$cols['date']] ?? '') ?: null) : null];
        }
        fclose($handle);
        if ($header === null) {
            throw new InvalidArgumentException(__('The statement is empty.'));
        }

        return $rows;
    }

    private static function find(array $header, array $names): ?int
    {
        foreach ($names as $name) {
            $i = array_search($name, $header, true);
            if ($i !== false) {
                return (int) $i;
            }
        }

        return null;
    }
}
