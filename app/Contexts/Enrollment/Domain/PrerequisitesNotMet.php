<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain;

use RuntimeException;

/**
 * استثناء مجال نقي — يُرمى عندما يحاول المتعلّم الالتحاق بمقرر
 * دون إكمال متطلّباته السابقة (E1).
 *
 * يحمل أرقام المقررات الناقصة فقط — لا HTTP، لا رسائل عرض.
 * التحويل إلى 422 يتولّاه المتحكّم أو Handler في طبقة HTTP.
 */
final class PrerequisitesNotMet extends RuntimeException
{
    /**
     * @param  list<int>  $missingCourseIds  معرّفات المقررات التي لم يُكملها المتعلّم.
     */
    public function __construct(
        private readonly array $missingCourseIds,
    ) {
        parent::__construct('المتطلّبات السابقة غير مكتملة.');
    }

    /**
     * @return list<int>
     */
    public function getMissingCourseIds(): array
    {
        return $this->missingCourseIds;
    }
}
