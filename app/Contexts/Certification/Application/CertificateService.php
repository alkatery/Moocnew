<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Infrastructure\Pdf\CertificatePdfRenderer;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
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

        return $this->issue(
            ['user_id' => $userId, 'course_id' => $courseId],
            $user->name,
            $course->title,
            'دورة',
        );
    }

    /**
     * Issue a certificate for completing an entire learning path (PRD-MV2
     * extension). Idempotent per (user, path).
     */
    public function issueForPath(int $userId, int $pathId): Certificate
    {
        $existing = Certificate::query()
            ->where('user_id', $userId)
            ->where('learning_path_id', $pathId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $user = User::query()->findOrFail($userId);
        $path = LearningPath::query()->findOrFail($pathId);

        return $this->issue(
            ['user_id' => $userId, 'learning_path_id' => $pathId],
            $user->name,
            $path->title,
            'المسار التخصصي',
        );
    }

    /**
     * @param  array<string, int>  $subject
     */
    private function issue(array $subject, string $holderName, string $subjectTitle, string $subjectLabel): Certificate
    {
        $certificate = Certificate::query()->create([
            ...$subject,
            'serial' => $this->uniqueSerial(),
            'verification_uuid' => (string) Str::uuid(),
            'issued_at' => Date::now(),
        ]);

        $verifyUrl = $this->verifyUrl($certificate->verification_uuid);
        $pdf = $this->renderer->render($certificate, $holderName, $subjectTitle, $verifyUrl, $subjectLabel);

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
