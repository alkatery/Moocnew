'use client';

/**
 * D4 — ضوابط مشغّل الفيديو (VideoControls)
 *
 * المتطلّبات (العقد §1.ب + §1.ج):
 * - أزرار سرعة 0.75/1/1.25/1.5/2× تضبط videoRef.current.playbackRate
 * - aria-pressed على الزرّ النشط
 * - إشعار الاستئناف: عند video_position > 0 يُعرض شريط «المتابعة من د:ث»
 *   مع زرّي «استئناف» و«من البداية»
 * - لا زرّ تنزيل / لا منتقي جودة (قرار العقد §0)
 * - لـ embed: لا تُعرض هذه الضوابط (يُتحكّم من الخارج بإخفاء المكوّن)
 */

import { useState, useEffect, useCallback } from 'react';
import { t } from '@/i18n/dictionary';

// معدّلات السرعة المدعومة (وفق العقد §1.ب)
const SPEED_OPTIONS = [0.75, 1, 1.25, 1.5, 2] as const;
type SpeedOption = (typeof SPEED_OPTIONS)[number];

interface VideoControlsProps {
  /** مرجع عنصر الفيديو */
  videoRef: React.RefObject<HTMLVideoElement | null>;
  /**
   * موضع الاستئناف بالثواني (من course progress)
   * 0 أو null = لا استئناف
   */
  resumeAt: number | null;
}

/**
 * تنسيق الثواني إلى «د:ث» (مثال: 125 → «2:05»)
 */
function formatTime(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${s.toString().padStart(2, '0')}`;
}

export function VideoControls({ videoRef, resumeAt }: VideoControlsProps) {
  // السرعة الحالية — تبدأ بـ 1×
  const [speed, setSpeed] = useState<SpeedOption>(1);
  // هل يظهر شريط الاستئناف؟
  const [showResume, setShowResume] = useState(false);

  // عند تغيّر resumeAt (درس جديد يُفتح): أظهر الإشعار إن كان الموضع > 0
  useEffect(() => {
    if (resumeAt && resumeAt > 0) {
      setShowResume(true);
    } else {
      setShowResume(false);
    }
    // إعادة ضبط السرعة لكل درس جديد
    setSpeed(1);
  }, [resumeAt]);

  // ضبط playbackRate عند تغيّر السرعة
  useEffect(() => {
    const vid = videoRef.current;
    if (!vid) return;
    vid.playbackRate = speed;
  }, [speed, videoRef]);

  // الاستئناف من الموضع المحفوظ
  const handleResume = useCallback(() => {
    const vid = videoRef.current;
    if (!vid || !resumeAt) return;
    vid.currentTime = resumeAt;
    void vid.play().catch(() => {});
    setShowResume(false);
  }, [videoRef, resumeAt]);

  // البداية من الصفر (لا يغيّر currentTime — يُخفي الإشعار فقط)
  const handleFromStart = useCallback(() => {
    const vid = videoRef.current;
    if (!vid) return;
    vid.currentTime = 0;
    setShowResume(false);
  }, [videoRef]);

  const resumeTimeLabel = resumeAt ? formatTime(resumeAt) : '';

  return (
    <div className="mt-2 flex flex-col gap-2" dir="rtl">
      {/* شريط إشعار الاستئناف — aria-live لإعلان ظهوره */}
      {showResume && resumeAt && resumeAt > 0 && (
        <div
          role="status"
          aria-live="polite"
          aria-atomic="true"
          className="flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
        >
          <span>
            {t('player.resumeFrom').replace('{time}', resumeTimeLabel)}
          </span>
          <button
            type="button"
            aria-label={`${t('player.resume')} — ${t('player.resumeFrom').replace('{time}', resumeTimeLabel)}`}
            onClick={handleResume}
            className="btn btn-ghost rounded-md border border-amber-400 bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900 hover:bg-amber-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-500"
          >
            {t('player.resume')}
          </button>
          <button
            type="button"
            aria-label={t('player.fromStart')}
            onClick={handleFromStart}
            className="btn btn-ghost rounded-md px-3 py-1 text-xs text-amber-700 underline hover:text-amber-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-500"
          >
            {t('player.fromStart')}
          </button>
        </div>
      )}

      {/* شريط ضوابط السرعة */}
      <div
        className="flex flex-wrap items-center gap-1"
        role="group"
        aria-label={t('player.speed')}
      >
        <span className="text-xs font-bold text-slate-500" aria-hidden>
          {t('player.speed')}:
        </span>
        {SPEED_OPTIONS.map((opt) => {
          const isActive = speed === opt;
          return (
            <button
              key={opt}
              type="button"
              aria-label={`${t('player.speed')} ${opt}×`}
              aria-pressed={isActive}
              onClick={() => setSpeed(opt)}
              className={`rounded-md px-2.5 py-1 text-xs font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500 ${
                isActive
                  ? 'bg-brand-600 text-white'
                  : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-100'
              }`}
            >
              {opt}×
            </button>
          );
        })}
      </div>
    </div>
  );
}

/**
 * تصدير دالة formatTime للاختبارات
 */
export { formatTime };
