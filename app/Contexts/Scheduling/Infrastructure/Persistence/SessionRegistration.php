<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $live_session_id
 * @property int $user_id
 */
final class SessionRegistration extends Model
{
    protected $fillable = ['live_session_id', 'user_id'];
}
