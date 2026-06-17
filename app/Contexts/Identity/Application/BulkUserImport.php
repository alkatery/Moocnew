<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bulk-creates student accounts from a CSV (name,email[,phone]) — the path
 * by which an institution onboards a whole cohort at once. Each created
 * account gets the student role and a random password (handed over out of
 * band); duplicate emails are skipped rather than failing the whole file,
 * and an optional course enrolls everyone as active students immediately.
 */
final class BulkUserImport
{
    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @return array{created: int, skipped: int, enrolled: int, errors: list<array{line: int, message: string}>}
     *
     * @throws ValidationException
     */
    public function handle(User $actor, string $path, ?Course $course = null): array
    {
        $rows = $this->parse($path);

        $created = 0;
        $skipped = 0;
        $enrolled = 0;
        $errors = [];
        $seen = [];

        foreach ($rows as [$line, $name, $email, $phone]) {
            if ($name === '' || $email === '') {
                $errors[] = ['line' => $line, 'message' => 'الاسم والبريد الإلكتروني مطلوبان.'];

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $line, 'message' => "البريد الإلكتروني غير صالح: {$email}"];

                continue;
            }

            $email = mb_strtolower($email);

            if (isset($seen[$email]) || User::query()->withTrashed()->where('email', $email)->exists()) {
                $skipped++;

                continue;
            }

            $seen[$email] = true;

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'password' => Str::password(12),
            ]);
            $user->assignRole(Role::Student->value);
            $created++;

            if ($course !== null) {
                $this->enrollments->enroll($user, $course);
                $enrolled++;
            }
        }

        $this->activity->log('users.bulk_imported', $actor, null, [
            'created' => $created,
            'skipped' => $skipped,
            'enrolled' => $enrolled,
            'course_id' => $course?->getKey(),
        ]);

        return [
            'created' => $created,
            'skipped' => $skipped,
            'enrolled' => $enrolled,
            'errors' => $errors,
        ];
    }

    /**
     * Parse the CSV into [line, name, email, phone] tuples. A header row
     * (name,email[,phone] in any order) is honoured when present; otherwise
     * columns are read positionally.
     *
     * @return list<array{int, string, string, string}>
     *
     * @throws ValidationException
     */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'تعذّرت قراءة الملف.']);
        }

        try {
            $rows = [];
            $line = 0;
            $map = ['name' => 0, 'email' => 1, 'phone' => 2];

            while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
                $line++;

                $cells = array_map(static fn ($cell): string => trim((string) $cell), $cells);

                if ($line === 1) {
                    // Strip a UTF-8 BOM (Excel exports) before header detection.
                    $cells[0] = ltrim($cells[0] ?? '', "\xEF\xBB\xBF");

                    $lowered = array_map(static fn (string $cell): string => mb_strtolower($cell), $cells);

                    if (in_array('email', $lowered, true)) {
                        $map = [
                            'name' => array_search('name', $lowered, true),
                            'email' => array_search('email', $lowered, true),
                            'phone' => array_search('phone', $lowered, true),
                        ];

                        continue;
                    }
                }

                if ($cells === ['']) {
                    continue; // blank line
                }

                $rows[] = [
                    $line,
                    is_int($map['name']) ? ($cells[$map['name']] ?? '') : '',
                    is_int($map['email']) ? ($cells[$map['email']] ?? '') : '',
                    is_int($map['phone']) ? ($cells[$map['phone']] ?? '') : '',
                ];
            }

            if (count($rows) > self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'file' => 'يتجاوز الملف الحد الأقصى ('.self::MAX_ROWS.' سطر).',
                ]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
