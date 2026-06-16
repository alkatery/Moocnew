'use client';

/**
 * D4 — لوحة البحث في نصّ الدرس (TranscriptSearch)
 *
 * المتطلّبات (العقد §1.أ):
 * - حقل بحث نصّي فوق لوحة النصّ
 * - تظليل آمن بـ <mark> (تقطيع النصّ — لا dangerouslySetInnerHTML)
 * - عدّاد نتائج بـ aria-live="polite"
 * - تنقّل بين النتائج (التالية/السابقة) مع scrollIntoView داخل اللوحة
 * - لا قفز زمني (النصّ خام بلا طوابع — قرار العقد §0)
 * - مطابقة حرفية substring غير حسّاسة للحالة (لا regex من إدخال المستخدم)
 */

import { useRef, useState, useCallback, useEffect, useMemo } from 'react';
import { t } from '@/i18n/dictionary';

interface TranscriptSearchProps {
  /** النصّ الكامل للدرس (nullable — اللوحة لا تظهر إن كان null) */
  transcript: string;
}

/**
 * تقطيع النصّ إلى أجزاء: نصّ عادي ونصّ مطابق
 * آمن — لا regex من إدخال المستخدم، بحث بـ indexOf تكراريّاً
 */
function splitByQuery(
  text: string,
  query: string,
): { text: string; match: boolean }[] {
  if (!query) return [{ text, match: false }];

  const lowerText = text.toLowerCase();
  const lowerQuery = query.toLowerCase();
  const parts: { text: string; match: boolean }[] = [];
  let cursor = 0;

  while (cursor < text.length) {
    const idx = lowerText.indexOf(lowerQuery, cursor);
    if (idx === -1) {
      // بقيّة النصّ بعد آخر تطابق
      parts.push({ text: text.slice(cursor), match: false });
      break;
    }
    // النصّ قبل التطابق
    if (idx > cursor) {
      parts.push({ text: text.slice(cursor, idx), match: false });
    }
    // النصّ المطابق
    parts.push({ text: text.slice(idx, idx + query.length), match: true });
    cursor = idx + query.length;
  }

  return parts;
}

export function TranscriptSearch({ transcript }: TranscriptSearchProps) {
  const [query, setQuery] = useState('');
  // الفهرس الحالي للنتيجة النشطة (يبدأ من 0)
  const [activeIdx, setActiveIdx] = useState(0);
  // مراجع عناصر <mark> لتمرير scrollIntoView
  const markRefs = useRef<(HTMLElement | null)[]>([]);
  // مرجع نافذة لوحة النصّ للتمرير الداخلي
  const panelRef = useRef<HTMLDivElement | null>(null);

  // حساب التقاطعات من النصّ والاستعلام
  const parts = useMemo(
    () => splitByQuery(transcript, query.trim()),
    [transcript, query],
  );

  // عدد التطابقات الكلّي
  const totalMatches = useMemo(
    () => parts.filter((p) => p.match).length,
    [parts],
  );

  // إعادة ضبط الفهرس النشط عند تغيّر الاستعلام
  useEffect(() => {
    setActiveIdx(0);
    markRefs.current = [];
  }, [query]);

  // التمرير إلى النتيجة النشطة
  useEffect(() => {
    if (totalMatches === 0) return;
    const el = markRefs.current[activeIdx];
    if (!el || typeof el.scrollIntoView !== 'function') return;
    // scrollIntoView داخل اللوحة فقط (block: nearest — لا تحريك الصفحة كلّها)
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, [activeIdx, totalMatches]);

  const goNext = useCallback(() => {
    if (totalMatches === 0) return;
    setActiveIdx((i) => (i + 1) % totalMatches);
  }, [totalMatches]);

  const goPrev = useCallback(() => {
    if (totalMatches === 0) return;
    setActiveIdx((i) => (i - 1 + totalMatches) % totalMatches);
  }, [totalMatches]);

  // نصّ عدّاد النتائج (يُعلَن بـ aria-live)
  const counterText = useMemo(() => {
    if (!query.trim()) return '';
    if (totalMatches === 0) return t('transcript.noResults');
    // «١ من ٣»
    return t('transcript.results')
      .replace('{n}', String(activeIdx + 1))
      .replace('{total}', String(totalMatches));
  }, [query, totalMatches, activeIdx]);

  // بناء عناصر JSX للنصّ المُظلَّل
  let matchCounter = -1;
  const renderedParts = parts.map((part, i) => {
    if (!part.match) {
      return <span key={i}>{part.text}</span>;
    }
    matchCounter += 1;
    const localIdx = matchCounter;
    const isActive = localIdx === activeIdx;
    return (
      <mark
        key={i}
        ref={(el) => {
          markRefs.current[localIdx] = el;
        }}
        // تمييز بصري إضافي للنتيجة النشطة غير معتمد على اللون وحده
        className={
          isActive
            ? 'rounded bg-amber-300 font-bold outline outline-2 outline-amber-500'
            : 'rounded bg-yellow-200'
        }
        aria-current={isActive ? 'true' : undefined}
      >
        {part.text}
      </mark>
    );
  });

  return (
    <div className="card mt-4" dir="rtl">
      {/* رأس اللوحة */}
      <div className="border-b border-slate-100 p-4">
        <h3 className="mb-3 font-bold text-slate-900">{t('transcript.panel')}</h3>

        {/* حقل البحث */}
        <div className="flex flex-wrap items-center gap-2">
          <label htmlFor="transcript-search" className="sr-only">
            {t('transcript.search')}
          </label>
          <input
            id="transcript-search"
            type="search"
            placeholder={t('transcript.search')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
            aria-label={t('transcript.search')}
          />

          {/* أزرار التنقّل بين النتائج */}
          <button
            type="button"
            aria-label={t('transcript.prevMatch')}
            disabled={totalMatches <= 1}
            onClick={goPrev}
            className="btn btn-ghost disabled:cursor-not-allowed disabled:opacity-40"
          >
            {/* سهم يمين (السابق في RTL) */}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
              <path d="M9 18l6-6-6-6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            <span className="sr-only">{t('transcript.prevMatch')}</span>
          </button>
          <button
            type="button"
            aria-label={t('transcript.nextMatch')}
            disabled={totalMatches <= 1}
            onClick={goNext}
            className="btn btn-ghost disabled:cursor-not-allowed disabled:opacity-40"
          >
            {/* سهم يسار (التالي في RTL) */}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
              <path d="M15 18l-6-6 6-6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            <span className="sr-only">{t('transcript.nextMatch')}</span>
          </button>
        </div>

        {/* عدّاد النتائج — aria-live لإعلان القارئ */}
        <div
          role="status"
          aria-live="polite"
          aria-atomic="true"
          className="mt-2 text-xs text-slate-500"
        >
          {counterText}
        </div>
      </div>

      {/* لوحة النصّ مع التظليل */}
      <div
        ref={panelRef}
        className="max-h-72 overflow-y-auto p-4 text-sm leading-relaxed text-slate-700"
      >
        {/* تلميح: لا قفز زمني — قرار العقد §0 */}
        {query.trim() === '' && (
          <p className="mb-3 text-xs text-slate-400 italic">
            {t('transcript.noTimestamps')}
          </p>
        )}
        <p className="whitespace-pre-wrap">{renderedParts}</p>
      </div>
    </div>
  );
}
