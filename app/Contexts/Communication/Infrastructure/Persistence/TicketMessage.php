<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/** @property int $ticket_id @property int $user_id @property string $body */
final class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'body'];
}
