<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $transaction_uuid
 * @property int $account_id
 * @property int $debit_minor
 * @property int $credit_minor
 */
final class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'transaction_uuid', 'account_id', 'debit_minor', 'credit_minor', 'currency', 'ref_type', 'ref_id', 'memo',
    ];

    protected function casts(): array
    {
        return ['debit_minor' => 'integer', 'credit_minor' => 'integer'];
    }
}
