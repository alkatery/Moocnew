<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communication;

use App\Contexts\Communication\Application\TicketService;
use App\Contexts\Communication\Infrastructure\Persistence\Ticket;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query()->latest();

        if (! $request->user()->can(Permission::Moderate->value)) {
            $query->where('user_id', $request->user()->getKey());
        }

        return response()->json(['data' => $query->paginate(20)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $ticket = $this->tickets->open($request->user(), $data['subject'], $data['body']);

        return response()->json(['data' => $ticket->load('messages')], 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAccess($request, $ticket);

        return response()->json(['data' => $ticket->load('messages')]);
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAccess($request, $ticket);
        $data = $request->validate(['body' => ['required', 'string']]);

        $message = $this->tickets->reply($ticket, $request->user(), $data['body']);

        return response()->json(['data' => $message], 201);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAccess($request, $ticket);

        return response()->json(['data' => $this->tickets->close($ticket)]);
    }

    private function authorizeAccess(Request $request, Ticket $ticket): void
    {
        $isOwner = $ticket->user_id === $request->user()->getKey();
        abort_unless($isOwner || $request->user()->can(Permission::Moderate->value), 403);
    }
}
