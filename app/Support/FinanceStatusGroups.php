<?php

namespace App\Support;

final class FinanceStatusGroups
{
    /** @var string[] */
    public const TRANSACTION_SUCCESS = ['paid', 'completed', 'succeeded', 'success'];

    /** @var string[] */
    public const TRANSACTION_PENDING = ['pending', 'processing'];

    /** @var string[] */
    public const TRANSACTION_FAILURE = ['failed', 'cancelled'];

    /** @return array{success: string[], pending: string[], failure: string[]} */
    public static function transactionGroups(): array
    {
        return [
            'success' => self::TRANSACTION_SUCCESS,
            'pending' => self::TRANSACTION_PENDING,
            'failure' => self::TRANSACTION_FAILURE,
        ];
    }

    /** @return string[] */
    public static function allKnownTransactionStatuses(): array
    {
        return array_values(array_unique(array_merge(
            self::TRANSACTION_SUCCESS,
            self::TRANSACTION_PENDING,
            self::TRANSACTION_FAILURE,
        )));
    }

    /**
     * SQL fragment: LOWER({column}) IN ('a','b',...) for raw queries.
     *
     * @param  string[]  $statuses
     */
    public static function sqlLowerStatusIn(string $column, array $statuses): string
    {
        return "LOWER({$column}) IN ('".implode("','", $statuses)."')";
    }
}
