<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Infrastructure\Pdf;

use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Renders a certificate to PDF bytes from a Blade template, embedding a QR
 * code that points at the public verification URL (PRD §5.ح). The QR is
 * generated as SVG (pure PHP, no image extension required) and inlined.
 */
final class CertificatePdfRenderer
{
    public function render(
        Certificate $certificate,
        string $holderName,
        string $courseTitle,
        string $verifyUrl,
        string $subjectLabel = 'دورة',
    ): string {
        $qrSvg = base64_encode(
            (string) QrCode::format('svg')->size(160)->margin(1)->generate($verifyUrl),
        );

        return Pdf::loadView('certificates.certificate', [
            'holderName' => $holderName,
            'courseTitle' => $courseTitle,
            'subjectLabel' => $subjectLabel,
            'serial' => $certificate->serial,
            'issuedAt' => $certificate->issued_at,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => 'data:image/svg+xml;base64,'.$qrSvg,
        ])
            ->setPaper('a4', 'landscape')
            ->output();
    }
}
