'use client';

import { useCallback, useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { AssignmentItem, BankQuestion, QuestionChoice, QuestionKind, QuizItem, Section } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { ErrorMsg } from '@/components/StatusMessage';
// C2: مراجعة/تصحيح تسليمات الواجبات — يُحمَّل كسلًا inline ضمن بطاقة الواجبات
import { SubmissionReview } from '@/components/studio/SubmissionReview';
// E5: منتقي استيراد الأسئلة — يُحمَّل كسلًا عند الفتح فقط
import { QuestionImportPicker } from '@/components/studio/QuestionImportPicker';

const KIND_LABELS: Record<QuestionKind, string> = {
  mcq: 'اختيار من متعدد',
  true_false: 'صح / خطأ',
  short_answer: 'إجابة قصيرة',
  // E4: أنواع جديدة
  dropdown: 'قائمة منسدلة',
  multi_select: 'اختيار متعدّد الإجابات',
  numerical: 'إدخال رقمي',
  regex: 'مطابقة نصّ (Regex)',
};

/**
 * Studio assessments builder: a per-course question bank (MCQ, true/false,
 * short answer), quizzes assembled from the bank with weight/section, and
 * graded assignments — the instructor's full grading toolkit.
 */
export function AssessmentsPanel({ courseSlug, sections }: { courseSlug: string; sections: Section[] }) {
  const [questions, setQuestions] = useState<BankQuestion[]>([]);
  const [quizzes, setQuizzes] = useState<QuizItem[]>([]);
  const [assignments, setAssignments] = useState<AssignmentItem[]>([]);
  const [err, setErr] = useState('');
  // C2: معرّف الواجب المفتوح لمراجعة تسليماته (null = لا شيء مفتوح)
  const [reviewAssignmentId, setReviewAssignmentId] = useState<number | null>(null);
  // E5: هل منتقي الاستيراد مفتوح؟
  const [importPickerOpen, setImportPickerOpen] = useState(false);

  const load = useCallback(() => {
    api<{ data: BankQuestion[] }>(`/assessment/courses/${courseSlug}/questions`).then((r) => setQuestions(r.data)).catch(() => undefined);
    api<{ data: QuizItem[] }>(`/assessment/courses/${courseSlug}/quizzes`).then((r) => setQuizzes(r.data)).catch(() => undefined);
    api<{ data: AssignmentItem[] }>(`/assessment/courses/${courseSlug}/assignments`).then((r) => setAssignments(r.data)).catch(() => undefined);
  }, [courseSlug]);
  useEffect(load, [load]);

  const sectionName = (id: number | null) => sections.find((s) => s.id === id)?.title ?? null;

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      {/* G5: role="alert" عبر ErrorMsg */}
      {err && <div className="lg:col-span-2"><ErrorMsg msg={err} /></div>}

      {/* ------------------------------------------------ question bank */}
      <div className="card mb-0 self-start">
        <div className="mb-1 flex items-center justify-between gap-2">
          <strong className="text-slate-900">بنك الأسئلة</strong>
          {/* E5: زرّ «استيراد من مكتبتي» — يفتح/يغلق منتقي الاستيراد */}
          <button
            type="button"
            className="btn btn-ghost text-xs"
            onClick={() => setImportPickerOpen((prev) => !prev)}
            aria-expanded={importPickerOpen}
            aria-controls="import-picker-panel"
            aria-label={t('import.open')}
          >
            {t('import.open')}
          </button>
        </div>
        <p className="mb-3 text-xs text-slate-500">أسئلة الدورة المُعاد استخدامها في الاختبارات — التصحيح آلي بالكامل.</p>

        {/* E5: منتقي الاستيراد — يُحمَّل كسلًا عند الفتح فقط */}
        {importPickerOpen && (
          <div id="import-picker-panel" className="mb-4">
            <QuestionImportPicker
              courseSlug={courseSlug}
              onImported={load}
              onClose={() => setImportPickerOpen(false)}
            />
          </div>
        )}

        {questions.length > 0 && (
          <ul className="mb-4 divide-y divide-slate-100">
            {questions.map((q) => (
              <li key={q.id} className="flex items-start justify-between gap-2 py-2.5">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-slate-800">{q.body}</p>
                  <span className="text-xs text-slate-500">{KIND_LABELS[q.type]} · {q.points} نقطة</span>
                </div>
                <button className="btn btn-ghost shrink-0 text-xs text-red-600"
                  onClick={() => void api(`/assessment/questions/${q.id}`, { method: 'DELETE' }).then(load).catch(() => setErr(t('common.error')))}>
                  حذف
                </button>
              </li>
            ))}
          </ul>
        )}
        <QuestionForm courseSlug={courseSlug} onCreated={load} />
      </div>

      <div className="space-y-6 self-start">
        {/* --------------------------------------------------- quizzes */}
        <div className="card mb-0">
          <strong className="text-slate-900">الاختبارات</strong>
          <p className="mb-3 text-xs text-slate-500">اختبارات مؤقتة بمحاولات محدودة، تُجمع أسئلتها من البنك ولها وزن في الدرجة النهائية.</p>
          {quizzes.length > 0 && (
            <ul className="mb-4 divide-y divide-slate-100">
              {quizzes.map((qz) => (
                <li key={qz.id} className="flex items-center justify-between gap-2 py-2.5">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-slate-800">{qz.title}</p>
                    <span className="text-xs text-slate-500">
                      {qz.questions_count ?? '—'} سؤالاً · نجاح {qz.pass_mark}% · وزن ×{qz.weight}
                      {sectionName(qz.section_id) ? ` · ${sectionName(qz.section_id)}` : ''}
                    </span>
                  </div>
                  <button className="btn btn-ghost shrink-0 text-xs text-red-600"
                    onClick={() => void api(`/assessment/quizzes/${qz.id}`, { method: 'DELETE' }).then(load).catch(() => setErr(t('common.error')))}>
                    حذف
                  </button>
                </li>
              ))}
            </ul>
          )}
          <QuizForm courseSlug={courseSlug} questions={questions} sections={sections} onCreated={load} />
        </div>

        {/* ------------------------------------------------ assignments */}
        <div className="card mb-0">
          <strong className="text-slate-900">الواجبات</strong>
          <p className="mb-3 text-xs text-slate-500">تسليمات نصية يصححها المدرّب يدوياً بدرجة من مجموع النقاط.</p>
          {assignments.length > 0 && (
            <ul className="mb-4 divide-y divide-slate-100">
              {assignments.map((a) => (
                <li key={a.id} className="py-2.5">
                  {/* سطر ملخّص الواجب + زر مراجعة التسليمات (C2) */}
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium text-slate-800">{a.title}</p>
                      <span className="text-xs text-slate-500">
                        {a.points} نقطة · وزن ×{a.weight}
                        {sectionName(a.section_id) ? ` · ${sectionName(a.section_id)}` : ''}
                        {a.due_at ? ` · تسليم قبل ${new Date(a.due_at).toLocaleDateString('ar')}` : ''}
                      </span>
                    </div>
                    {/* C2: زر «مراجعة التسليمات» — يفتح/يغلق SubmissionReview inline */}
                    <button
                      className="btn btn-ghost shrink-0 text-xs"
                      onClick={() =>
                        setReviewAssignmentId((prev) => (prev === a.id ? null : a.id))
                      }
                      aria-expanded={reviewAssignmentId === a.id}
                      aria-controls={`submission-review-${a.id}`}
                    >
                      {reviewAssignmentId === a.id ? 'إغلاق المراجعة' : t('review.open')}
                    </button>
                  </div>

                  {/* C2: مكوّن SubmissionReview — يُحمَّل كسلًا عند الفتح فقط */}
                  {reviewAssignmentId === a.id && (
                    <div id={`submission-review-${a.id}`}>
                      <SubmissionReview
                        assignment={a}
                        onClose={() => setReviewAssignmentId(null)}
                      />
                    </div>
                  )}
                </li>
              ))}
            </ul>
          )}
          <AssignmentForm courseSlug={courseSlug} sections={sections} onCreated={load} />
        </div>
      </div>
    </div>
  );
}

// --------------------------------------------------------------------------

function QuestionForm({ courseSlug, onCreated }: { courseSlug: string; onCreated: () => void }) {
  const [kind, setKind] = useState<QuestionKind>('mcq');
  const [body, setBody] = useState('');
  const [points, setPoints] = useState('1');
  // choices + correct للأنواع القائمة وdropdown وmulti_select
  const [choices, setChoices] = useState<QuestionChoice[]>([{ id: 'a', text: '' }, { id: 'b', text: '' }]);
  const [correctIds, setCorrectIds] = useState<string[]>([]);
  // true_false
  const [tfCorrect, setTfCorrect] = useState(true);
  // short_answer
  const [accepted, setAccepted] = useState('');
  // numerical (E4)
  const [numValue, setNumValue] = useState('');
  const [numTolerance, setNumTolerance] = useState('0');
  // regex (E4)
  const [regexPattern, setRegexPattern] = useState('');
  const [regexIgnoreCase, setRegexIgnoreCase] = useState(false);
  const [explanation, setExplanation] = useState('');
  const [msg, setMsg] = useState('');

  /** يُعيد true ويضبط msg عند خطأ تحقّق واجهة للأنواع الجديدة */
  function validateE4(): boolean {
    if (kind === 'dropdown') {
      if (correctIds.length !== 1) { setMsg(t('q.dropdown.validationError')); return false; }
    }
    if (kind === 'multi_select') {
      if (correctIds.length < 1) { setMsg(t('q.multi_select.validationError')); return false; }
    }
    if (kind === 'numerical') {
      const val = numValue.trim();
      const tol = parseFloat(numTolerance);
      if (!val || isNaN(Number(val)) || isNaN(tol) || tol < 0) {
        setMsg(t('q.numerical.validationError')); return false;
      }
    }
    if (kind === 'regex') {
      const p = regexPattern.trim();
      if (!p || p.length > 200) { setMsg(t('q.regex.validationError')); return false; }
    }
    return true;
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setMsg('');

    // حساب correct وconfig وchoices حسب النوع
    let correct: unknown;
    let config: Record<string, unknown> | null = null;
    let choicesPayload: QuestionChoice[] | null = null;

    if (kind === 'mcq') {
      correct = correctIds;
      choicesPayload = choices.filter((c) => c.text.trim() !== '');
    } else if (kind === 'true_false') {
      correct = tfCorrect;
    } else if (kind === 'short_answer') {
      correct = accepted.split('،').flatMap((s) => s.split(',')).map((s) => s.trim()).filter(Boolean);
    } else if (kind === 'dropdown') {
      if (!validateE4()) return;
      correct = correctIds; // مصفوفة عنصر واحد
      choicesPayload = choices.filter((c) => c.text.trim() !== '');
    } else if (kind === 'multi_select') {
      if (!validateE4()) return;
      correct = correctIds;
      choicesPayload = choices.filter((c) => c.text.trim() !== '');
    } else if (kind === 'numerical') {
      if (!validateE4()) return;
      correct = [numValue.trim().replace(/[٫،,]/g, '.')]; // تطبيع الفاصلة العربية
      config = { tolerance: parseFloat(numTolerance) };
    } else if (kind === 'regex') {
      if (!validateE4()) return;
      correct = [regexPattern.trim()];
      config = { flags: regexIgnoreCase ? 'i' : '' };
    }

    try {
      await api(`/assessment/courses/${courseSlug}/questions`, {
        method: 'POST',
        body: {
          type: kind,
          body,
          points: parseInt(points || '1', 10),
          choices: choicesPayload,
          correct,
          config,
          explanation: explanation || null,
        },
      });
      // إعادة ضبط الحقول
      setBody('');
      setChoices([{ id: 'a', text: '' }, { id: 'b', text: '' }]);
      setCorrectIds([]);
      setAccepted('');
      setNumValue('');
      setNumTolerance('0');
      setRegexPattern('');
      setRegexIgnoreCase(false);
      setExplanation('');
      setMsg('أُضيف السؤال ✓');
      onCreated();
    } catch (err) { setMsg(err instanceof Error ? err.message : t('common.error')); }
  }

  /** حقل خيارات مشترك بين MCQ وdropdown وmulti_select */
  function ChoicesBlock({ mode }: { mode: 'checkbox' | 'radio' }) {
    return (
      <fieldset className="space-y-2 border-0 p-0">
        <legend className="sr-only">
          {mode === 'radio' ? t('q.dropdown.correctHint') : t('q.multi_select.correctHint')}
        </legend>
        {choices.map((c, i) => (
          <div key={c.id} className="flex items-center gap-2">
            {mode === 'radio' ? (
              <input
                type="radio"
                name={`correct-choice-${kind}`}
                aria-label={`خيار ${i + 1} — إجابة صحيحة`}
                checked={correctIds.includes(c.id)}
                onChange={() => setCorrectIds([c.id])}
              />
            ) : (
              <input
                type="checkbox"
                aria-label={`خيار ${i + 1} — إجابة صحيحة`}
                checked={correctIds.includes(c.id)}
                onChange={(e) => setCorrectIds((p) => e.target.checked ? [...p, c.id] : p.filter((x) => x !== c.id))}
              />
            )}
            <input
              className="input m-0 flex-1"
              placeholder={`الخيار ${i + 1}`}
              value={c.text}
              aria-label={`نص الخيار ${i + 1}`}
              onChange={(e) => setChoices((p) => p.map((x) => x.id === c.id ? { ...x, text: e.target.value } : x))}
            />
            {choices.length > 2 && (
              <button
                type="button"
                className="text-red-500"
                aria-label={`حذف الخيار ${i + 1}`}
                onClick={() => {
                  setChoices((p) => p.filter((x) => x.id !== c.id));
                  setCorrectIds((p) => p.filter((x) => x !== c.id));
                }}
              >
                ✕
              </button>
            )}
          </div>
        ))}
        <button
          type="button"
          className="btn btn-ghost text-xs"
          onClick={() => setChoices((p) => [...p, { id: String.fromCharCode(97 + p.length) + Date.now().toString(36), text: '' }])}
        >
          + خيار
        </button>
        <p className="text-xs text-slate-500">
          {mode === 'radio' ? t('q.dropdown.correctHint') : t('q.multi_select.correctHint')}
        </p>
      </fieldset>
    );
  }

  return (
    <form className="space-y-3 rounded-xl border border-slate-200 p-3" onSubmit={(e) => void submit(e)}>
      <strong className="text-sm text-slate-700">سؤال جديد</strong>
      <div className="grid grid-cols-2 gap-2">
        <label className="sr-only" htmlFor="q-kind-select">نوع السؤال</label>
        <select
          id="q-kind-select"
          className="input m-0"
          value={kind}
          onChange={(e) => { setKind(e.target.value as QuestionKind); setCorrectIds([]); }}
        >
          {(Object.keys(KIND_LABELS) as QuestionKind[]).map((k) => <option key={k} value={k}>{KIND_LABELS[k]}</option>)}
        </select>
        <label className="sr-only" htmlFor="q-points-input">النقاط</label>
        <input
          id="q-points-input"
          className="input m-0"
          type="number"
          min="1"
          dir="ltr"
          value={points}
          onChange={(e) => setPoints(e.target.value)}
          aria-label="النقاط"
          placeholder="النقاط"
        />
      </div>
      <label className="sr-only" htmlFor="q-body-textarea">نص السؤال</label>
      <textarea
        id="q-body-textarea"
        className="input m-0"
        placeholder="نص السؤال"
        value={body}
        required
        onChange={(e) => setBody(e.target.value)}
        aria-label="نص السؤال"
      />

      {/* MCQ القائم — صناديق اختيار */}
      {kind === 'mcq' && (
        <fieldset className="space-y-2 border-0 p-0">
          <legend className="sr-only">خيارات الإجابة — علّم الصحيح</legend>
          {choices.map((c, i) => (
            <div key={c.id} className="flex items-center gap-2">
              <input
                type="checkbox"
                aria-label={`خيار ${i + 1} — إجابة صحيحة`}
                checked={correctIds.includes(c.id)}
                onChange={(e) => setCorrectIds((p) => e.target.checked ? [...p, c.id] : p.filter((x) => x !== c.id))}
              />
              <input
                className="input m-0 flex-1"
                placeholder={`الخيار ${i + 1}`}
                value={c.text}
                aria-label={`نص الخيار ${i + 1}`}
                onChange={(e) => setChoices((p) => p.map((x) => x.id === c.id ? { ...x, text: e.target.value } : x))}
              />
              {choices.length > 2 && (
                <button type="button" className="text-red-500" aria-label={`حذف الخيار ${i + 1}`}
                  onClick={() => { setChoices((p) => p.filter((x) => x.id !== c.id)); setCorrectIds((p) => p.filter((x) => x !== c.id)); }}>
                  ✕
                </button>
              )}
            </div>
          ))}
          <button type="button" className="btn btn-ghost text-xs"
            onClick={() => setChoices((p) => [...p, { id: String.fromCharCode(97 + p.length) + Date.now().toString(36), text: '' }])}>
            + خيار
          </button>
          <p className="text-xs text-slate-500">علّم ☑ بجانب الخيار/الخيارات الصحيحة.</p>
        </fieldset>
      )}

      {kind === 'true_false' && (
        <fieldset className="border-0 p-0">
          <legend className="sr-only">الإجابة الصحيحة</legend>
          <div className="flex gap-2">
            <button type="button" className={`chip ${tfCorrect ? 'chip-active' : ''}`} onClick={() => setTfCorrect(true)}>الإجابة: صح</button>
            <button type="button" className={`chip ${!tfCorrect ? 'chip-active' : ''}`} onClick={() => setTfCorrect(false)}>الإجابة: خطأ</button>
          </div>
        </fieldset>
      )}

      {kind === 'short_answer' && (
        <div>
          <label className="mb-1 block text-xs text-slate-500" htmlFor="q-accepted">الإجابات المقبولة (افصل بفاصلة)</label>
          <input id="q-accepted" className="input m-0" placeholder="الإجابات المقبولة (افصل بينها بفاصلة)" value={accepted}
            onChange={(e) => setAccepted(e.target.value)} />
        </div>
      )}

      {/* E4: dropdown — خيارات + راديو لتحديد الإجابة الصحيحة */}
      {kind === 'dropdown' && <ChoicesBlock mode="radio" />}

      {/* E4: multi_select — خيارات + صناديق لتحديد ≥1 إجابة صحيحة */}
      {kind === 'multi_select' && <ChoicesBlock mode="checkbox" />}

      {/* E4: numerical — قيمة صحيحة + هامش خطأ */}
      {kind === 'numerical' && (
        <fieldset className="space-y-2 rounded-lg border border-slate-100 p-3">
          <legend className="text-xs font-semibold text-slate-600">إعدادات السؤال الرقمي</legend>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="mb-1 block text-xs text-slate-500" htmlFor="q-num-value">
                {t('q.numerical.correctValue')}
              </label>
              <input
                id="q-num-value"
                className="input m-0"
                type="number"
                dir="ltr"
                placeholder={t('q.numerical.placeholder')}
                value={numValue}
                onChange={(e) => setNumValue(e.target.value)}
                step="any"
                required
              />
            </div>
            <div>
              <label className="mb-1 block text-xs text-slate-500" htmlFor="q-num-tolerance">
                {t('q.numerical.tolerance')}
              </label>
              <input
                id="q-num-tolerance"
                className="input m-0"
                type="number"
                dir="ltr"
                min="0"
                step="any"
                value={numTolerance}
                onChange={(e) => setNumTolerance(e.target.value)}
                aria-describedby="q-num-tolerance-hint"
              />
              <p id="q-num-tolerance-hint" className="mt-1 text-xs text-slate-400">{t('q.numerical.toleranceHint')}</p>
            </div>
          </div>
        </fieldset>
      )}

      {/* E4: regex — نمط + خيار تجاهل حالة الأحرف */}
      {kind === 'regex' && (
        <fieldset className="space-y-2 rounded-lg border border-slate-100 p-3">
          <legend className="text-xs font-semibold text-slate-600">إعدادات مطابقة النص</legend>
          <div>
            <label className="mb-1 block text-xs text-slate-500" htmlFor="q-regex-pattern">
              {t('q.regex.pattern')}
            </label>
            <input
              id="q-regex-pattern"
              className="input m-0 font-mono"
              type="text"
              dir="ltr"
              placeholder={t('q.regex.patternPlaceholder')}
              value={regexPattern}
              onChange={(e) => setRegexPattern(e.target.value)}
              maxLength={200}
              aria-describedby="q-regex-hint"
              required
            />
            <p id="q-regex-hint" className="mt-1 text-xs text-slate-400">{t('q.regex.hint')}</p>
          </div>
          <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={regexIgnoreCase}
              onChange={(e) => setRegexIgnoreCase(e.target.checked)}
              aria-label={t('q.regex.ignoreCase')}
            />
            {t('q.regex.ignoreCase')}
          </label>
        </fieldset>
      )}

      <div>
        <label className="mb-1 block text-xs text-slate-500" htmlFor="q-explanation">شرح الإجابة (اختياري)</label>
        <input
          id="q-explanation"
          className="input m-0"
          placeholder="شرح الإجابة (يظهر للطالب بعد التسليم — تغذية راجعة)"
          value={explanation}
          onChange={(e) => setExplanation(e.target.value)}
        />
      </div>
      <div className="flex items-center gap-2">
        <button className="btn">إضافة السؤال</button>
        {/* G5: role مناسب — نجاح (✓) أو خطأ */}
        {msg && (
          msg.includes('✓')
            ? <span className="text-xs text-slate-500" role="status">{msg}</span>
            : <span className="text-xs text-red-700" role="alert">{msg}</span>
        )}
      </div>
    </form>
  );
}

function QuizForm({ courseSlug, questions, sections, onCreated }: {
  courseSlug: string; questions: BankQuestion[]; sections: Section[]; onCreated: () => void;
}) {
  const [title, setTitle] = useState('');
  const [passMark, setPassMark] = useState('60');
  const [timeLimit, setTimeLimit] = useState('');
  const [attempts, setAttempts] = useState('');
  const [weight, setWeight] = useState('1');
  const [drawCount, setDrawCount] = useState('');
  const [sectionId, setSectionId] = useState('');
  const [picked, setPicked] = useState<number[]>([]);
  const [msg, setMsg] = useState('');

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setMsg('');
    try {
      await api(`/assessment/courses/${courseSlug}/quizzes`, {
        method: 'POST',
        body: {
          title,
          pass_mark: parseInt(passMark || '60', 10),
          time_limit_minutes: timeLimit ? parseInt(timeLimit, 10) : null,
          max_attempts: attempts ? parseInt(attempts, 10) : null,
          weight: parseInt(weight || '1', 10),
          draw_count: drawCount ? parseInt(drawCount, 10) : null,
          section_id: sectionId ? parseInt(sectionId, 10) : null,
          question_ids: picked,
        },
      });
      setTitle(''); setPicked([]); setMsg('أُنشئ الاختبار ✓');
      onCreated();
    } catch (err) { setMsg(err instanceof Error ? err.message : t('common.error')); }
  }

  return (
    <form className="space-y-3 rounded-xl border border-slate-200 p-3" onSubmit={(e) => void submit(e)}>
      <strong className="text-sm text-slate-700">اختبار جديد</strong>
      <input className="input m-0" placeholder="عنوان الاختبار" value={title} required onChange={(e) => setTitle(e.target.value)} />
      <div className="grid grid-cols-2 gap-2">
        <label className="text-xs text-slate-500">درجة النجاح %
          <input className="input m-0 mt-1" type="number" min="0" max="100" dir="ltr" value={passMark} onChange={(e) => setPassMark(e.target.value)} />
        </label>
        <label className="text-xs text-slate-500">الوزن في الدرجة النهائية
          <input className="input m-0 mt-1" type="number" min="1" max="100" dir="ltr" value={weight} onChange={(e) => setWeight(e.target.value)} />
        </label>
        <label className="text-xs text-slate-500">مدة الاختبار (دقائق، اختياري)
          <input className="input m-0 mt-1" type="number" min="1" dir="ltr" value={timeLimit} onChange={(e) => setTimeLimit(e.target.value)} />
        </label>
        <label className="text-xs text-slate-500">أقصى محاولات (اختياري)
          <input className="input m-0 mt-1" type="number" min="1" dir="ltr" value={attempts} onChange={(e) => setAttempts(e.target.value)} />
        </label>
        <label className="text-xs text-slate-500">سحب عشوائي: عدد الأسئلة لكل طالب (اختياري)
          <input className="input m-0 mt-1" type="number" min="1" dir="ltr" value={drawCount} onChange={(e) => setDrawCount(e.target.value)}
            placeholder="فارغ = كل الأسئلة" />
        </label>
      </div>
      {sections.length > 0 && (
        <select className="input m-0" value={sectionId} onChange={(e) => setSectionId(e.target.value)}>
          <option value="">بدون ربط بقسم (عام للدورة)</option>
          {sections.map((s) => <option key={s.id} value={s.id}>القسم: {s.title}</option>)}
        </select>
      )}
      <div className="max-h-40 space-y-1 overflow-y-auto rounded-lg bg-slate-50 p-2">
        {questions.length === 0
          ? <p className="text-xs text-slate-500">أضف أسئلة إلى البنك أولاً.</p>
          : questions.map((q) => (
            <label key={q.id} className="flex items-center gap-2 text-sm text-slate-600">
              <input type="checkbox" checked={picked.includes(q.id)}
                onChange={(e) => setPicked((p) => e.target.checked ? [...p, q.id] : p.filter((x) => x !== q.id))} />
              <span className="truncate">{q.body}</span>
            </label>
          ))}
      </div>
      <div className="flex items-center gap-2">
        <button className="btn" disabled={picked.length === 0}>إنشاء الاختبار</button>
        {/* G5: role مناسب — نجاح (✓) أو خطأ */}
        {msg && (
          msg.includes('✓')
            ? <span className="text-xs text-slate-500" role="status">{msg}</span>
            : <span className="text-xs text-red-700" role="alert">{msg}</span>
        )}
      </div>
    </form>
  );
}

function AssignmentForm({ courseSlug, sections, onCreated }: {
  courseSlug: string; sections: Section[]; onCreated: () => void;
}) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [points, setPoints] = useState('100');
  const [weight, setWeight] = useState('1');
  const [dueAt, setDueAt] = useState('');
  const [sectionId, setSectionId] = useState('');
  const [rubric, setRubric] = useState<{ id: string; title: string; max_points: string }[]>([]);
  const [msg, setMsg] = useState('');

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setMsg('');
    try {
      await api(`/assessment/courses/${courseSlug}/assignments`, {
        method: 'POST',
        body: {
          title, description: description || null,
          points: parseInt(points || '100', 10),
          weight: parseInt(weight || '1', 10),
          due_at: dueAt || null,
          section_id: sectionId ? parseInt(sectionId, 10) : null,
          rubric: rubric.length > 0
            ? rubric.filter((r) => r.title.trim() !== '').map((r) => ({ id: r.id, title: r.title, max_points: parseInt(r.max_points || '1', 10) }))
            : null,
        },
      });
      setTitle(''); setDescription(''); setRubric([]); setMsg('أُنشئ الواجب ✓');
      onCreated();
    } catch (err) { setMsg(err instanceof Error ? err.message : t('common.error')); }
  }

  return (
    <form className="space-y-3 rounded-xl border border-slate-200 p-3" onSubmit={(e) => void submit(e)}>
      <strong className="text-sm text-slate-700">واجب جديد</strong>
      <input className="input m-0" placeholder="عنوان الواجب" value={title} required onChange={(e) => setTitle(e.target.value)} />
      <textarea className="input m-0" placeholder="تعليمات الواجب" value={description} onChange={(e) => setDescription(e.target.value)} />
      <div className="grid grid-cols-2 gap-2">
        <label className="text-xs text-slate-500">النقاط الكلية
          <input className="input m-0 mt-1" type="number" min="1" dir="ltr" value={points} onChange={(e) => setPoints(e.target.value)} />
        </label>
        <label className="text-xs text-slate-500">الوزن في الدرجة النهائية
          <input className="input m-0 mt-1" type="number" min="1" max="100" dir="ltr" value={weight} onChange={(e) => setWeight(e.target.value)} />
        </label>
      </div>
      <label className="block text-xs text-slate-500">موعد التسليم (اختياري)
        <input className="input m-0 mt-1" type="datetime-local" dir="ltr" value={dueAt} onChange={(e) => setDueAt(e.target.value)} />
      </label>
      {sections.length > 0 && (
        <select className="input m-0" value={sectionId} onChange={(e) => setSectionId(e.target.value)}>
          <option value="">بدون ربط بقسم (عام للدورة)</option>
          {sections.map((s) => <option key={s.id} value={s.id}>القسم: {s.title}</option>)}
        </select>
      )}
      <div className="rounded-lg bg-slate-50 p-2">
        <div className="mb-1 flex items-center justify-between">
          <span className="text-xs font-bold text-slate-600">معايير التصحيح (Rubric — اختياري)</span>
          <button type="button" className="btn btn-ghost text-xs"
            onClick={() => setRubric((p) => [...p, { id: 'c' + Date.now().toString(36) + p.length, title: '', max_points: '10' }])}>
            + معيار
          </button>
        </div>
        {rubric.map((r) => (
          <div key={r.id} className="mb-1 flex items-center gap-2">
            <input className="input m-0 flex-1" placeholder="المعيار (مثل: وضوح الفكرة)" value={r.title}
              onChange={(e) => setRubric((p) => p.map((x) => x.id === r.id ? { ...x, title: e.target.value } : x))} />
            <input className="input m-0 w-20" type="number" min="1" dir="ltr" title="الدرجة القصوى" value={r.max_points}
              onChange={(e) => setRubric((p) => p.map((x) => x.id === r.id ? { ...x, max_points: e.target.value } : x))} />
            <button type="button" className="text-red-500" aria-label="حذف المعيار"
              onClick={() => setRubric((p) => p.filter((x) => x.id !== r.id))}>✕</button>
          </div>
        ))}
        {rubric.length > 0 && (
          <p className="text-xs text-slate-500">مجموع المعايير يصبح درجة الطالب (يُقصّ عند نقاط الواجب).</p>
        )}
      </div>
      <div className="flex items-center gap-2">
        <button className="btn">إنشاء الواجب</button>
        {/* G5: role مناسب — نجاح (✓) أو خطأ */}
        {msg && (
          msg.includes('✓')
            ? <span className="text-xs text-slate-500" role="status">{msg}</span>
            : <span className="text-xs text-red-700" role="alert">{msg}</span>
        )}
      </div>
    </form>
  );
}
