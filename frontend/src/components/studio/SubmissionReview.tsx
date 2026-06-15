'use client';

/**
 * C2 — SubmissionReview
 * مكوّن مراجعة وتصحيح تسليمات واجب بعينه (prop: AssignmentItem).
 *
 * التدفّق:
 *  1. يجلب قائمة التسليمات  GET /assessment/assignments/{id}/submissions
 *  2. يعرض كل تسليم: اسم الطالب، حالة مُصحَّح/غير مُصحَّح، تاريخ التسليم،
 *     الدرجة إن وُجدت، المحتوى، ورابط تنزيل الملف.
 *  3. نموذج التصحيح: معايير Rubric (إن وُجدت) أو درجة مباشرة + تغذية راجعة.
 *  4. تأكيد إعادة التصحيح عند تجاوز درجة سابقة.
 *  5. POST /assessment/submissions/{id}/grade → تحديث الحالة بالاستجابة.
 *  6. ترقيم الصفحات عبر meta.current_page / meta.last_page.
 */

import { useCallback, useEffect, useState } from 'react';
import { api, getToken } from '@/lib/api';
import type { AssignmentItem, AssignmentSubmission, RubricCriterion } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';

// --------------------------------------------------------------------------
// أنواع داخلية

interface SubmissionsPage {
  data: AssignmentSubmission[];
  meta: { current_page: number; last_page: number; total: number };
}

// --------------------------------------------------------------------------
// المكوّن الرئيسي

export function SubmissionReview({ assignment, onClose }: { assignment: AssignmentItem; onClose: () => void }) {
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<SubmissionsPage | null>(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState('');
  // فهرس التسليم المفتوح حالياً (null = لا شيء)
  const [openId, setOpenId] = useState<number | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setErr('');
    api<SubmissionsPage>(`/assessment/assignments/${assignment.id}/submissions?page=${page}`)
      .then((r) => { setResult(r); setLoading(false); })
      .catch(() => { setErr(t('review.error')); setLoading(false); });
  }, [assignment.id, page]);

  useEffect(load, [load]);

  /** تحديث تسليم واحد في القائمة بعد التصحيح */
  const handleGraded = (updated: AssignmentSubmission) => {
    setResult((prev) =>
      prev
        ? { ...prev, data: prev.data.map((s) => (s.id === updated.id ? updated : s)) }
        : prev,
    );
    // أغلق نموذج التسليم بعد الحفظ
    setOpenId(null);
  };

  const meta = result?.meta;

  return (
    <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
      {/* رأس المنطقة */}
      <div className="mb-4 flex items-center justify-between gap-2">
        <div>
          <h3 className="text-sm font-bold text-slate-900">
            {t('review.open')}: {assignment.title}
          </h3>
          <p className="text-xs text-slate-500">
            {assignment.points} نقطة
            {assignment.rubric && assignment.rubric.length > 0
              ? ` · ${assignment.rubric.length} معيار تصحيح`
              : ''}
          </p>
        </div>
        <button
          className="btn btn-ghost text-xs"
          onClick={onClose}
          aria-label="إغلاق مراجعة التسليمات"
        >
          ✕ إغلاق
        </button>
      </div>

      {/* حالة التحميل */}
      {loading && <p className="text-sm text-slate-500">{t('common.loading')}</p>}

      {/* حالة الخطأ */}
      {!loading && err && <ErrorMsg msg={err} />}

      {/* حالة الفراغ */}
      {!loading && !err && result?.data.length === 0 && (
        <p className="text-sm text-slate-500" role="status">
          {t('review.empty')}
        </p>
      )}

      {/* قائمة التسليمات */}
      {!loading && !err && result && result.data.length > 0 && (
        <>
          <ul className="divide-y divide-slate-200">
            {result.data.map((sub) => (
              <li key={sub.id} className="py-3">
                {/* سطر ملخّص التسليم */}
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="min-w-0">
                    {/* اسم الطالب */}
                    <p className="truncate text-sm font-medium text-slate-800">
                      {sub.student?.name ?? `طالب #${sub.user_id}`}
                    </p>
                    <span className="text-xs text-slate-500">
                      {new Date(sub.submitted_at).toLocaleString('ar')}
                      {sub.graded_at && sub.grade !== null
                        ? ` · الدرجة: ${sub.grade} / ${assignment.points}`
                        : ''}
                    </span>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    {/* شارة الحالة */}
                    <StatusBadge graded={sub.graded_at !== null} />
                    {/* زر فتح/إغلاق التفاصيل */}
                    <button
                      className="btn btn-ghost text-xs"
                      onClick={() => setOpenId(openId === sub.id ? null : sub.id)}
                      aria-expanded={openId === sub.id}
                      aria-controls={`submission-detail-${sub.id}`}
                    >
                      {openId === sub.id ? 'إغلاق' : 'فتح للتصحيح'}
                    </button>
                  </div>
                </div>

                {/* تفاصيل التسليم + نموذج التصحيح */}
                {openId === sub.id && (
                  <div
                    id={`submission-detail-${sub.id}`}
                    className="mt-3 rounded-lg border border-slate-200 bg-white p-4"
                  >
                    <SubmissionDetail
                      submission={sub}
                      assignment={assignment}
                      onGraded={handleGraded}
                    />
                  </div>
                )}
              </li>
            ))}
          </ul>

          {/* ترقيم الصفحات */}
          {meta && meta.last_page > 1 && (
            <div className="mt-4 flex items-center justify-between gap-2 text-sm text-slate-600">
              <button
                className="btn btn-ghost text-xs disabled:opacity-40"
                disabled={meta.current_page <= 1}
                onClick={() => setPage((p) => p - 1)}
                aria-label="الصفحة السابقة"
              >
                ← السابق
              </button>
              <span>
                الصفحة {meta.current_page} من {meta.last_page}
                {meta.total != null ? ` (${meta.total} تسليم)` : ''}
              </span>
              <button
                className="btn btn-ghost text-xs disabled:opacity-40"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                aria-label="الصفحة التالية"
              >
                التالي →
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

// --------------------------------------------------------------------------
// شارة حالة التصحيح

function StatusBadge({ graded }: { graded: boolean }) {
  return graded ? (
    <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
      {t('review.graded')}
    </span>
  ) : (
    <span className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
      {t('review.ungraded')}
    </span>
  );
}

// --------------------------------------------------------------------------
// تفاصيل التسليم ونموذج التصحيح

function SubmissionDetail({
  submission,
  assignment,
  onGraded,
}: {
  submission: AssignmentSubmission;
  assignment: AssignmentItem;
  onGraded: (updated: AssignmentSubmission) => void;
}) {
  const hasRubric = Array.isArray(assignment.rubric) && assignment.rubric.length > 0;

  // حالة نموذج التصحيح
  const [rubricScores, setRubricScores] = useState<Record<string, string>>(() => {
    // تهيئة من درجات سابقة عند إعادة التصحيح
    if (hasRubric && submission.rubric_scores) {
      return Object.fromEntries(
        Object.entries(submission.rubric_scores).map(([k, v]) => [k, String(v)]),
      );
    }
    return {};
  });
  const [directGrade, setDirectGrade] = useState<string>(
    submission.grade !== null && !hasRubric ? String(submission.grade) : '',
  );
  const [feedback, setFeedback] = useState<string>(submission.feedback ?? '');
  const [saving, setSaving] = useState(false);
  const [saveErr, setSaveErr] = useState('');
  const [saveOk, setSaveOk] = useState('');

  /** مجموع درجات المعايير */
  const rubricTotal = hasRubric
    ? (assignment.rubric as RubricCriterion[]).reduce((sum, c) => {
        const val = parseInt(rubricScores[c.id] ?? '0', 10);
        return sum + (isNaN(val) ? 0 : Math.min(val, c.max_points));
      }, 0)
    : 0;

  async function save() {
    setSaveErr('');
    setSaveOk('');

    // تأكيد إعادة التصحيح عند وجود درجة سابقة
    if (submission.graded_at !== null) {
      const prevGrade = submission.grade ?? '—';
      const confirmMsg = `${t('review.regradeConfirm')} (الدرجة الحالية: ${prevGrade})`;
      if (!window.confirm(confirmMsg)) return;
    }

    setSaving(true);
    try {
      const body: Record<string, unknown> = { feedback: feedback.trim() || null };
      if (hasRubric) {
        // بناء rubric_scores: { [criterion.id]: number }
        const scores: Record<string, number> = {};
        for (const c of assignment.rubric as RubricCriterion[]) {
          scores[c.id] = Math.max(0, parseInt(rubricScores[c.id] ?? '0', 10) || 0);
        }
        body.rubric_scores = scores;
      } else {
        body.grade = parseInt(directGrade || '0', 10);
      }

      const res = await api<{ data: AssignmentSubmission }>(
        `/assessment/submissions/${submission.id}/grade`,
        { method: 'POST', body },
      );
      setSaveOk(t('review.saved'));
      onGraded(res.data);
    } catch (e) {
      setSaveErr(e instanceof Error ? e.message : t('common.error'));
    } finally {
      setSaving(false);
    }
  }

  const gradeFormId = `grade-form-${submission.id}`;

  return (
    <div className="space-y-4">
      {/* ------ محتوى التسليم ------ */}
      {submission.content && (
        <div>
          <p className="mb-1 text-xs font-semibold text-slate-600">إجابة الطالب</p>
          <p className="whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
            {submission.content}
          </p>
        </div>
      )}

      {/* ------ رابط تنزيل الملف ------ */}
      {submission.has_file && (
        <div>
          {submission.file_url ? (
            /* رابط محمي — يُفتح بتوكن المصادقة الحالي */
            <FileDownloadLink url={submission.file_url} />
          ) : (
            <p className="text-xs text-slate-500">
              ملف مُرفق (الرابط يُوفَّر من الخادم عند جهوزيّته — §2.ب)
            </p>
          )}
        </div>
      )}

      {/* ------ نموذج التصحيح ------ */}
      <form
        id={gradeFormId}
        className="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
        onSubmit={(e) => { e.preventDefault(); void save(); }}
        aria-label={`نموذج تصحيح تسليم ${submission.student?.name ?? submission.id}`}
      >
        <p className="text-xs font-bold text-slate-700">التصحيح</p>

        {/* -- حقول معايير Rubric -- */}
        {hasRubric ? (
          <div className="space-y-2">
            {(assignment.rubric as RubricCriterion[]).map((c) => {
              const fieldId = `rubric-${submission.id}-${c.id}`;
              return (
                <div key={c.id}>
                  <label
                    htmlFor={fieldId}
                    className="mb-0.5 block text-xs text-slate-600"
                  >
                    {c.title}
                    <span className="ms-1 text-slate-400">(من {c.max_points})</span>
                  </label>
                  <input
                    id={fieldId}
                    type="number"
                    dir="ltr"
                    className="input m-0 w-24"
                    min={0}
                    max={c.max_points}
                    value={rubricScores[c.id] ?? ''}
                    placeholder="0"
                    onChange={(e) =>
                      setRubricScores((prev) => ({ ...prev, [c.id]: e.target.value }))
                    }
                  />
                </div>
              );
            })}
            {/* مجموع المعايير */}
            <p className="text-xs font-semibold text-slate-700">
              {t('review.total')}: {Math.min(rubricTotal, assignment.points)} من {assignment.points}
            </p>
          </div>
        ) : (
          /* -- حقل درجة مباشرة -- */
          <div>
            <label
              htmlFor={`direct-grade-${submission.id}`}
              className="mb-0.5 block text-xs text-slate-600"
            >
              {t('review.grade')}
              <span className="ms-1 text-slate-400">(0 – {assignment.points})</span>
            </label>
            <input
              id={`direct-grade-${submission.id}`}
              type="number"
              dir="ltr"
              className="input m-0 w-32"
              min={0}
              max={assignment.points}
              value={directGrade}
              placeholder="0"
              onChange={(e) => setDirectGrade(e.target.value)}
            />
          </div>
        )}

        {/* حقل التغذية الراجعة */}
        <div>
          <label
            htmlFor={`feedback-${submission.id}`}
            className="mb-0.5 block text-xs text-slate-600"
          >
            {t('review.feedback')}
            <span className="ms-1 text-slate-400">(اختياري)</span>
          </label>
          <textarea
            id={`feedback-${submission.id}`}
            className="input m-0 min-h-[72px]"
            value={feedback}
            onChange={(e) => setFeedback(e.target.value)}
            placeholder="ملاحظاتك على هذا التسليم…"
          />
        </div>

        {/* رسائل الحالة */}
        {saveErr && <ErrorMsg msg={saveErr} />}
        {saveOk && <SuccessMsg msg={saveOk} />}

        {/* أزرار */}
        <div className="flex items-center gap-2">
          <button
            type="submit"
            className="btn"
            disabled={saving}
            aria-busy={saving}
          >
            {saving ? t('common.loading') : t('review.save')}
          </button>
        </div>
      </form>
    </div>
  );
}

// --------------------------------------------------------------------------
// رابط تنزيل الملف المحمي (يُرسَل التوكن في الرأس عبر fetch + objectURL)

function FileDownloadLink({ url }: { url: string }) {
  const [downloading, setDownloading] = useState(false);
  const [dlErr, setDlErr] = useState('');

  async function download() {
    setDlErr('');
    setDownloading(true);
    try {
      const token = getToken();
      // file_url رابط مطلق من الخادم (§2.ب) — يُرسَل التوكن في الرأس لأن المسار محمي بـ Sanctum
      const res = await fetch(url, {
        headers: {
          Authorization: `Bearer ${token ?? ''}`,
          Accept: '*/*',
        },
      });
      if (!res.ok) throw new Error(`${res.status}`);
      const blob = await res.blob();
      const href = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = href;
      // استخراج اسم الملف من Content-Disposition إن وُجد
      const cd = res.headers.get('content-disposition') ?? '';
      const match = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd);
      a.download = match?.[1]?.trim() ?? 'submission-file';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(href);
    } catch {
      setDlErr('تعذّر تنزيل الملف — حاول مرة أخرى.');
    } finally {
      setDownloading(false);
    }
  }

  return (
    <div>
      <button
        type="button"
        className="btn btn-ghost text-xs text-brand-600"
        onClick={() => void download()}
        disabled={downloading}
        aria-busy={downloading}
      >
        {downloading ? t('common.loading') : `⬇ ${t('review.downloadFile')}`}
      </button>
      {dlErr && <ErrorMsg msg={dlErr} />}
    </div>
  );
}
