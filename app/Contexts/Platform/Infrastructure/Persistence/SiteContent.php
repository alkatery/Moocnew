<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Infrastructure\Persistence;

use App\Contexts\Platform\Domain\Content\SiteContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * One editable piece of site content (a branding value, a marketing string,
 * a colour, or an image URL).
 *
 * @property string $key
 * @property string $group
 * @property SiteContentType $type
 * @property string $label
 * @property string|null $value
 * @property int $position
 */
final class SiteContent extends Model
{
    protected $fillable = [
        'key',
        'group',
        'type',
        'label',
        'value',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => SiteContentType::class,
            'position' => 'integer',
        ];
    }
}
