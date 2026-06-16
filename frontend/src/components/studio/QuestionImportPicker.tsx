'use client';

/**
 * E5 — منتقي استيراد الأسئلة من مكتبة المؤلّف.
 *
 * يُفتح من زرّ «استيراد من مكتبتي» في بنك أسئلة المقرر (AssessmentsPanel).
 * يجلب GET /assessment/courses/{slug}/questions/importable?q=…
 * ويُرسل POST /assessment/courses/{slug}/questions/import { source_question_ids }.
 *
 * الحالات: تحميل · فراغ · خطأ · نجاح — كلّها عبر أدوار a11y صحيحة.
 * RTL/عربي · تباين AA · fieldset/legend · aria labels.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '@/lib/api';
import type { ImportableQuestion, QuestionKind } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';

// -------------------------------------------------------------------
// ثوابت

const KIND_LABELS: Record<QuestionKind, string> = {
  mcq: 'اختيار من متعدد',
  true_false: 'صح / خطأ',
  short_answer: 'إجابة قصيرة',
  dropdown: 'قائمة منسدلة',
  multi_select: 'اختيار متعدّد الإجابات',
  numerical: 'إدخال رقمي',
  regex: 'مطابقة نصّ (Regex)',
};

// -------------------------------------------------------------------
// الواجهة

interface Props {
  /** slug المقرر الهدف (سيُستورَد إليه) */
  courseSlug: string;
  /** يُستدعى بعد استيراد ناجح لإعادة تحميل بنك المقرر */
  onImported: () => void;
  /** يُستدعى لإغلاق المنتقي */
  onClose: () => void;
}

// -------------------------------------------------------------------
// نوع استجابة importable (مرقّمة)
interface ImportableResponse {
  data: ImportableQuestion[];
  meta?: { current_page: number; last_page: number; total?: number };
}

// -------------------------------------------------------------------
// مكوّن

export function QuestionImportPicker({ courseSlug, onImported, onClose }: Props) {
  const [questions, setQuestions] = useState<ImportableQuestion[]>([]);
  const [loading, setLoading] = useState(false);
  const [fetchErr, setFetchErr] = useState('');
  const [importErr, setImportErr] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [importing, setImporting] = useState(false);

  // حقول التصفية
  const [query, setQuery] = useState('');
  const [sourceFilter, setSourceFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');

  // debounce للبحث النصّي
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // استخراج قائمة المقررات المصدر المتاحة (من بيانات الصفحة الحالية)
  const sourceCourses = Array.from(
    new Map(
      questions.map((q) => [q.source_course.id, q.source_course]),
    ).values(),
  );

  // -------------------------------------------------------------------
  // جلب الأسئلة القابلة للاستيراد

  const fetchQuestions = useCallback(
    async (q: string, sourceCourseId: string, type: string) => {
      setLoading(true);
      setFetchErr('');
      try {
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (sourceCourseId) params.set('source_course_id', sourceCourseId);
        if (type) params.set('type', type);
        const qs = params.toString();
        const url = `/assessment/courses/${courseSlug}/questions/importable${qs ? `?${qs}` : ''}`;
        const res = await api<ImportableResponse>(url);
        setQuestions(res.data ?? []);
      } catch {
        setFetchErr(t('import.error'));
        setQuestions([]);
      } finally {
        setLoading(false);
      }
    },
    [courseSlug],
  );

  // التحميل الأولي
  useEffect(() => {
    void fetchQuestions('', '', '');
  }, [fetchQuestions]);

  // -------------------------------------------------------------------
  // معالجة تغيير حقل البحث مع debounce

  function handleQueryChange(value: string) {
    setQuery(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      void fetchQuestions(value, sourceFilter, typeFilter);
    }, 350);
  }

  function handleSourceChange(value: string) {
    setSourceFilter(value);
    void fetchQuestions(query, value, typeFilter);
  }

  function handleTypeChange(value: string) {
    setTypeFilter(value);
    void fetchQuestions(query, sourceFilter, value);
  }

  // -------------------------------------------------------------------
  // اختيار / إلغاء اختيار

  function toggleQuestion(id: number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggleAll(checked: boolean) {
    if (checked) {
      setSelected(new Set(questions.map((q) => q.id)));
    } else {
      setSelected(new Set());
    }
  }

  // -------------------------------------------------------------------
  // الاستيراد

  async function handleImport() {
    if (selected.size === 0) return;
    setImporting(true);
    setImportErr('');
    setSuccessMsg('');
    try {
      await api(`/assessment/courses/${courseSlug}/questions/import`, {
        method: 'POST',
        body: { source_question_ids: Array.from(selected) },
      });
      const n = selected.size;
      setSuccessMsg(t('import.done').replace('{n}', String(n)));
      setSelected(new Set());
      onImported();
      // إغلاق المنتقي بعد رسالة قصيرة
      setTimeout(onClose, 1500);
    } catch {
      setImportErr(t('import.importError'));
    } finally {
      setImporting(false);
    }
  }

  // -------------------------------------------------------------------
  // حالة مختارة الكل
  const allSelected =
    questions.length > 0 && questions.every((q) => selected.has(q.id));
  const someSelected =
    !allSelected && questions.some((q) => selected.has(q.id));

  // -------------------------------------------------------------------
  // العرض

  return (
    <section
      className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
      aria-label={t('import.title')}
    >
      {/* رأس المنتقي */}
      <div className="mb-4 flex items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-slate-800" id="import-picker-title">
          {t('import.title')}
        </h2>
        <button
          type="button"
          className="btn btn-ghost text-xs"
          onClick={onClose}
          aria-label={t('import.close')}
        >
          ✕
        </button>
      </div>

      {/* شريط التصفية */}
      <div className="mb-3 grid gap-2 sm:grid-cols-3">
        {/* حقل البحث */}
        <div>
          <label htmlFor="import-search" className="sr-only">
            {t('import.search')}
          </label>
          <input
            id="import-search"
            type="search"
            className="input m-0 w-full"
            placeholder={t('import.search')}
            value={query}
            onChange={(e) => handleQueryChange(e.target.value)}
            aria-label={t('import.search')}
          />
        </div>

        {/* مُنتقي المقرر المصدر */}
        <div>
          <label htmlFor="import-source-filter" className="sr-only">
            {t('import.sourceFilter')}
          </label>
          <select
            id="import-source-filter"
            className="input m-0 w-full"
            value={sourceFilter}
            onChange={(e) => handleSourceChange(e.target.value)}
            aria-label={t('import.sourceFilter')}
          >
            <option value="">{t('import.allCourses')}</option>
            {sourceCourses.map((c) => (
              <option key={c.id} value={String(c.id)}>
                {c.title}
              </option>
            ))}
          </select>
        </div>

        {/* مُنتقي النوع */}
        <div>
          <label htmlFor="import-type-filter" className="sr-only">
            {t('import.typeFilter')}
          </label>
          <select
            id="import-type-filter"
            className="input m-0 w-full"
            value={typeFilter}
            onChange={(e) => handleTypeChange(e.target.value)}
            aria-label={t('import.typeFilter')}
          >
            <option value="">{t('import.allTypes')}</option>
            {(Object.keys(KIND_LABELS) as QuestionKind[]).map((k) => (
              <option key={k} value={k}>
                {KIND_LABELS[k]}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* رسائل الحالة */}
      {fetchErr && <ErrorMsg msg={fetchErr} />}
      {importErr && <ErrorMsg msg={importErr} />}
      {successMsg && <SuccessMsg msg={successMsg} />}

      {/* حالة التحميل */}
      {loading && (
        <p className="py-4 text-center text-sm text-slate-500" role="status" aria-live="polite">
          {t('common.loading')}
        </p>
      )}

      {/* القائمة */}
      {!loading && !fetchErr && (
        <>
          {questions.length === 0 ? (
            /* حالة الفراغ */
            <p className="py-6 text-center text-sm text-slate-500" role="status">
              {query || sourceFilter || typeFilter
                ? t('import.emptySearch')
                : t('import.empty')}
            </p>
          ) : (
            <fieldset className="mb-3 border-0 p-0">
              <legend className="sr-only">{t('import.title')}</legend>

              {/* تحديد الكل */}
              <label className="mb-2 flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                <input
                  type="checkbox"
                  checked={allSelected}
                  ref={(el) => {
                    if (el) el.indeterminate = someSelected;
                  }}
                  onChange={(e) => toggleAll(e.target.checked)}
                  aria-label="تحديد كل الأسئلة"
                />
                تحديد الكل ({questions.length})
              </label>

              {/* قائمة الأسئلة */}
              <ul className="max-h-64 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-100">
                {questions.map((q) => (
                  <li key={q.id} className="flex items-start gap-2 px-3 py-2.5">
                    <input
                      type="checkbox"
                      id={`import-q-${q.id}`}
                      checked={selected.has(q.id)}
                      onChange={() => toggleQuestion(q.id)}
                      aria-label={`اختيار: ${q.body.slice(0, 60)}`}
                      className="mt-0.5 shrink-0"
                    />
                    <label
                      htmlFor={`import-q-${q.id}`}
                      className="min-w-0 flex-1 cursor-pointer"
                    >
                      <p
                        className="truncate text-sm text-slate-800"
                        title={q.body}
                      >
                        {q.body}
                      </p>
                      <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                        <span className="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">
                          {KIND_LABELS[q.type] ?? q.type}
                        </span>
                        <span>{q.points} نقطة</span>
                        {q.choices_count != null && (
                          <span>{q.choices_count} خيار</span>
                        )}
                        <span
                          className="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700"
                          title={`المقرر المصدر: ${q.source_course.title}`}
                        >
                          {q.source_course.title}
                        </span>
                      </div>
                    </label>
                  </li>
                ))}
              </ul>
            </fieldset>
          )}

          {/* زرّ الاستيراد */}
          <div className="flex items-center justify-between gap-2">
            <button
              type="button"
              className="btn"
              disabled={selected.size === 0 || importing}
              onClick={() => void handleImport()}
              aria-label={
                selected.size === 0
                  ? t('import.selected').replace('{n}', '0')
                  : t('import.selected').replace('{n}', String(selected.size))
              }
            >
              {importing
                ? t('common.loading')
                : t('import.selected').replace('{n}', String(selected.size))}
            </button>
            <span className="text-xs text-slate-400">
              {selected.size > 0
                ? `${selected.size} مختار`
                : ''}
            </span>
          </div>
        </>
      )}
    </section>
  );
}
