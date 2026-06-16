'use client';

/**
 * D4 — شريط التنقّل بين الدروس (LessonNav)
 *
 * المتطلّبات (العقد §1.د):
 * - يتلقّى التسلسل المسطّح للدروس + الفهرس النشط + دالة التنقّل
 * - «الدرس السابق» معطَّل عند أول درس؛ «الدرس التالي» معطَّل عند آخر درس
 * - disabled + aria-disabled عند الحدود
 * - يُعرض لكل أنواع الدروس (ليس الفيديو فقط)
 * - يظهر عنوان الدرس السابق/التالي بجوار الزرّ (تحسين اختياري — §1.د)
 * - RTL: السابق يميناً، التالي يساراً (منطق RTL)
 */

import { t } from '@/i18n/dictionary';
import type { Lesson } from '@/lib/types';

interface LessonNavProps {
  /** التسلسل الخطّي المسطّح لكل دروس الدورة (بترتيب القسم ثم الدرس) */
  lessons: Lesson[];
  /** فهرس الدرس النشط في المصفوفة المسطّحة (-1 إن لم يُختر درس) */
  activeIndex: number;
  /** دالة الانتقال — تُستدعى بالدرس المستهدف */
  onNavigate: (lesson: Lesson) => void;
}

export function LessonNav({ lessons, activeIndex, onNavigate }: LessonNavProps) {
  const hasPrev = activeIndex > 0;
  const hasNext = activeIndex >= 0 && activeIndex < lessons.length - 1;

  const prevLesson = hasPrev ? lessons[activeIndex - 1] : null;
  const nextLesson = hasNext ? lessons[activeIndex + 1] : null;

  return (
    <nav
      aria-label="التنقّل بين الدروس"
      dir="rtl"
      className="mt-3 flex items-center justify-between gap-3"
    >
      {/* زرّ الدرس السابق — يميناً في RTL */}
      <button
        type="button"
        aria-label={t('lessonNav.prev')}
        disabled={!hasPrev}
        aria-disabled={!hasPrev}
        onClick={() => {
          if (prevLesson) onNavigate(prevLesson);
        }}
        className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500"
      >
        {/* سهم يمين (سابق في RTL) */}
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          aria-hidden
          className="shrink-0"
        >
          <path
            d="M9 18l6-6-6-6"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
        <span className="flex flex-col items-start">
          <span className="text-xs text-slate-500">{t('lessonNav.prev')}</span>
          {prevLesson && (
            <span className="max-w-[140px] truncate text-xs font-semibold text-slate-700" title={prevLesson.title}>
              {prevLesson.title}
            </span>
          )}
        </span>
      </button>

      {/* زرّ الدرس التالي — يساراً في RTL */}
      <button
        type="button"
        aria-label={t('lessonNav.next')}
        disabled={!hasNext}
        aria-disabled={!hasNext}
        onClick={() => {
          if (nextLesson) onNavigate(nextLesson);
        }}
        className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500"
      >
        <span className="flex flex-col items-end">
          <span className="text-xs text-slate-500">{t('lessonNav.next')}</span>
          {nextLesson && (
            <span className="max-w-[140px] truncate text-xs font-semibold text-slate-700" title={nextLesson.title}>
              {nextLesson.title}
            </span>
          )}
        </span>
        {/* سهم يسار (تالٍ في RTL) */}
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          aria-hidden
          className="shrink-0"
        >
          <path
            d="M15 18l-6-6 6-6"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </button>
    </nav>
  );
}
