<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Contexts\Content\Infrastructure\Persistence\ContactMessage;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreContactMessageRequest;
use App\Http\Resources\ContactMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public contact form intake plus the staff triage list (PRD §5.ز —
 * support inbox). Submission is unauthenticated but rate-limited.
 */
final class ContactController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $message = ContactMessage::query()->create($request->validated());

        return (new ContactMessageResource($message))->response()->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(Permission::Moderate->value), 403);

        $messages = ContactMessage::query()
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 50));

        return ContactMessageResource::collection($messages);
    }

    public function handle(Request $request, ContactMessage $message): ContactMessageResource
    {
        abort_unless($request->user()->can(Permission::Moderate->value), 403);

        $message->markHandled();

        return new ContactMessageResource($message->fresh());
    }
}
