<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Infrastructure\Pdf;

use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Platform\Application\FeatureFlags;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Renders a certificate to PDF bytes from a Blade template, embedding a QR
 * code that points at the public verification URL (PRD §5.ح). The QR is
 * generated as SVG (pure PHP, no image extension required) and inlined.
 * When the NELC licence number is configured it is printed on the
 * certificate (NELC compliance).
 */
final class CertificatePdfRenderer
{
    public function __construct(
        private readonly FeatureFlags $features,
    ) {}

    public function render(
        Certificate $certificate,
        string $holderName,
        string $courseTitle,
        string $verifyUrl,
        string $subjectLabel = 'دورة',
        ?int $grade = null,
    ): string {
        $qrSvg = base64_encode(
            (string) QrCode::format('svg')->size(160)->margin(1)->generate($verifyUrl),
        );

        return Pdf::loadView('certificates.certificate', [
            'holderName' => $holderName,
            'courseTitle' => $courseTitle,
            'subjectLabel' => $subjectLabel,
            'grade' => $grade,
            'serial' => $certificate->serial,
            'issuedAt' => $certificate->issued_at,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => 'data:image/svg+xml;base64,'.$qrSvg,
            'nelcLicense' => $this->features->nelcLicenseNumber(),
        ])
            ->setPaper('a4', 'landscape')
            ->output();
    }
}
