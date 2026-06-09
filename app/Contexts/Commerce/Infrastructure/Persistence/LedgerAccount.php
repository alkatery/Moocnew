<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Persistence;

use App\Contexts\Commerce\Domain\Ledger\AccountType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property AccountType $type
 * @property int|null $owner_id
 * @property string $currency
 */
final class LedgerAccount extends Model
{
    protected $fillable = ['type', 'owner_id', 'currency'];

    protected function casts(): array
    {
        return ['type' => AccountType::class, 'owner_id' => 'integer'];
    }
}
