<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Certification;

use App\Contexts\Certification\Application\CertificateService;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CertificateController extends Controller
{
    /**
     * Public verification (PRD §5.ح): anyone with the UUID (via the QR code)
     * can confirm a certificate is genuine. Returns only non-sensitive
     * attestation data.
     */
    public function verify(string $uuid): JsonResponse
    {
        $certificate = Certificate::query()
            ->with(['user', 'course', 'path'])
            ->where('verification_uuid', $uuid)
            ->first();

        if ($certificate === null) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'serial' => $certificate->serial,
            'holder_name' => $certificate->user->name,
            'subject_type' => $certificate->learning_path_id !== null ? 'learning_path' : 'course',
            'course_title' => $certificate->subjectTitle(),
            'issued_at' => $certificate->issued_at->toIso8601String(),
        ]);
    }

    /**
     * The learner's own list of certificates.
     */
    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::query()
            ->with(['course', 'path'])
            ->where('user_id', $request->user()->getKey())
            ->latest('issued_at')
            ->get()
            ->map(fn (Certificate $c): array => [
                'serial' => $c->serial,
                'verification_uuid' => $c->verification_uuid,
                'subject_type' => $c->learning_path_id !== null ? 'learning_path' : 'course',
                'course_title' => $c->subjectTitle(),
                'issued_at' => $c->issued_at->toIso8601String(),
            ]);

        return response()->json(['data' => $certificates]);
    }

    /**
     * Download the PDF — restricted to the holder.
     */
    public function download(Request $request, Certificate $certificate, CertificateService $service): StreamedResponse
    {
        abort_unless($certificate->user_id === $request->user()->getKey(), 403);
        abort_if($certificate->pdf_path === null, 404);

        $disk = Storage::disk($service->disk());
        abort_unless($disk->exists($certificate->pdf_path), 404);

        return $disk->download($certificate->pdf_path, "certificate-{$certificate->serial}.pdf");
    }
}
