<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Application;

use App\Contexts\Communication\Infrastructure\Persistence\Ticket;
use App\Contexts\Communication\Infrastructure\Persistence\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Support ticketing linking learners to supervisors (PRD §5.ز).
 */
final class TicketService
{
    public function open(User $user, string $subject, string $body): Ticket
    {
        return DB::transaction(function () use ($user, $subject, $body): Ticket {
            $ticket = Ticket::query()->create(['user_id' => $user->getKey(), 'subject' => $subject]);
            $ticket->messages()->create(['user_id' => $user->getKey(), 'body' => $body]);

            return $ticket;
        });
    }

    public function reply(Ticket $ticket, User $user, string $body): TicketMessage
    {
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['ticket' => ['التذكرة مغلقة.']]);
        }

        return $ticket->messages()->create(['user_id' => $user->getKey(), 'body' => $body]);
    }

    public function assign(Ticket $ticket, User $staff): Ticket
    {
        $ticket->update(['assigned_to' => $staff->getKey()]);

        return $ticket;
    }

    public function close(Ticket $ticket): Ticket
    {
        $ticket->update(['status' => 'closed']);

        return $ticket;
    }
}
