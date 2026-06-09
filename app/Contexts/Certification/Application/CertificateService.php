<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Infrastructure\Pdf\CertificatePdfRenderer;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Issues course completion certificates (PRD §5.ح): a unique serial, a
 * public verification UUID, and a rendered PDF stored on a private disk.
 * Idempotent — re-issuing for the same (user, course) returns the existing
 * certificate.
 */
final class CertificateService
{
    public function __construct(
        private readonly CertificatePdfRenderer $renderer,
    ) {}

    public function issueFor(int $userId, int $courseId): Certificate
    {
        $existing = Certificate::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $user = User::query()->findOrFail($userId);
        $course = Course::query()->findOrFail($courseId);

        $certificate = Certificate::query()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'serial' => $this->uniqueSerial(),
            'verification_uuid' => (string) Str::uuid(),
            'issued_at' => Date::now(),
        ]);

        $verifyUrl = $this->verifyUrl($certificate->verification_uuid);
        $pdf = $this->renderer->render($certificate, $user->name, $course->title, $verifyUrl);

        $path = "certificates/{$certificate->verification_uuid}.pdf";
        Storage::disk($this->disk())->put($path, $pdf);

        $certificate->update(['pdf_path' => $path]);

        return $certificate;
    }

    public function verifyUrl(string $uuid): string
    {
        return url("/api/v1/certificates/verify/{$uuid}");
    }

    public function disk(): string
    {
        return (string) config('certification.disk', 'local');
    }

    private function uniqueSerial(): string
    {
        do {
            $serial = 'MOOC-'.Date::now()->year.'-'.strtoupper(Str::random(8));
        } while (Certificate::query()->where('serial', $serial)->exists());

        return $serial;
    }
}
