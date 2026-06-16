<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Domain;

/**
 * D3 — تردّدات ملخّص الإشعارات المتاحة للمستخدم.
 *
 * off:    لا ملخّص دوري — الإشعارات تصل فوراً عبر قنواتها المعتادة (افتراضي).
 * daily:  ملخّص بريدي يومي يجمّع نشاط الأربع والعشرين ساعة الماضية.
 * weekly: ملخّص بريدي أسبوعي كل يوم أحد.
 */
enum DigestFrequency: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
