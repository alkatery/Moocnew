<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Infrastructure\Persistence;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Domain\Course\VideoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lesson within a section (PRD §6).
 *
 * @property int $id
 * @property int $section_id
 * @property string $title
 * @property LessonType $type
 * @property string|null $video_id
 * @property VideoStatus $video_status
 * @property bool $is_free_preview
 */
final class Lesson extends Model
{
    protected $fillable = [
        'section_id',
        'title',
        'type',
        'content',
        'asset_path',
        'video_id',
        'video_status',
        'position',
        'is_free_preview',
    ];

    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'video_status' => VideoStatus::class,
            'is_free_preview' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
