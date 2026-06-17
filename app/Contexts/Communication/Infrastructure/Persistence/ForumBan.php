<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/** @property int $course_id @property int $user_id */
final class ForumBan extends Model
{
    protected $fillable = ['course_id', 'user_id', 'banned_by', 'reason'];
}
