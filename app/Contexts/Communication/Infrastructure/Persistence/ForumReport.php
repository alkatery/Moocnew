<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/** @property int $post_id @property int $reporter_id @property string $reason */
final class ForumReport extends Model
{
    protected $fillable = ['post_id', 'reporter_id', 'reason', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
