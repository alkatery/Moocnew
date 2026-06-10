'use client';

import { useEffect, useMemo, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';

interface RunnerQuestion {
  id: number;
  type: 'mcq' | 'true_false' | 'short_answer';
  body: string;
  choices: { id: string; text: string }[] | null;
  points: number;
}

interface AttemptInfo { id: number; score: number | null }

interface FeedbackRow {
  question_id: number;
  body: string;
  is_correct: boolean;
  correct: unknown;
  explanation: string | null;
  points: number;
}

/**
 * The learner's quiz runner: starts (or resumes) an attempt, renders the
 * served questions (a frozen random draw when configured), counts down the
 * time limit, and after submission shows the score with instant
 * per-question feedback and the instructor's explanations.
 */
export default function QuizRunnerPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const [attempt, setAttempt] = useState<AttemptInfo | null>(null);
  const [questions, setQuestions] = useState<RunnerQuestion[]>([]);
  const [answers, setAnswers] = useState<Record<number, unknown>>({});
  const [result, setResult] = useState<{ score: number; passed: boolean } | null>(null);
  const [feedback, setFeedback] = useState<FeedbackRow[]>([]);
  const [deadline, setDeadline] = useState<number | null>(null);
  const [now, setNow] = useState(Date.now());
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ attempt: { id: number; started_at?: string }; questions: RunnerQuestion[]; quiz?: { time_limit_minutes: number | null } }>(
      `/assessment/quizzes/${id}/attempts`, { method: 'POST' },
    )
      .then((r) => {
        setAttempt({ id: r.attempt.id, score: null });
        setQuestions(r.questions);
      })
      .catch((e) => setErr(e instanceof ApiError ? e.message : t('common.error')));
  }, [id]);

  // Lightweight countdown ticker (deadline set only when a limit exists).
  useEffect(() => {
    if (deadline === null) return;
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, [deadline]);

  const remaining = useMemo(() => {
    if (deadline === null) return null;
    return Math.max(0, Math.floor((deadline - now) / 1000));
  }, [deadline, now]);

  async function submit() {
    if (!attempt) return;
    setBusy(true); setErr('');
    try {
      const res = await api<{ data: { score: number; passed: boolean }; feedback: FeedbackRow[] }>(
        `/assessment/attempts/${attempt.id}/submit`,
        { method: 'POST', body: { answers } },
      );
      setResult({ score: res.data.score, passed: res.data.passed });
      setFeedback(res.feedback ?? []);
    } catch (e) {
      setErr(e instanceof ApiError ? e.message : t('common.error'));
    } finally { setBusy(false); }
  }

  if (err && !questions.length) {
    return (
      <section>
        <PageHeader title="الاختبار" crumbs={[{ label: 'الاختبار' }]} />
        <p className="error">{err}</p>
      </section>
    );
  }

  // ---------------------------------------------------------- results view
  if (result) {
    return (
      <section>
        <PageHeader title="نتيجة الاختبار" crumbs={[{ label: 'النتيجة' }]} />
        <div className="card text-center">
          <div className={`text-5xl font-extrabold ${result.passed ? 'text-emerald-600' : 'text-red-500'}`}>
            {result.score}%
          </div>
          <p className={`mt-2 font-bold ${result.passed ? 'text-emerald-700' : 'text-red-600'}`}>
            {result.passed ? '🎉 اجتزت الاختبار بنجاح' : 'لم تبلغ درجة النجاح بعد — راجع التغذية الراجعة وحاول مجدداً'}
          </p>
          <button className="btn mt-4" onClick={() => router.back()}>العودة للدورة</button>
        </div>

        <h2 className="mb-3 mt-6">التغذية الراجعة الفورية</h2>
        {feedback.map((f, i) => (
          <div key={f.question_id} className={`card border-s-4 ${f.is_correct ? 'border-s-emerald-400' : 'border-s-red-400'}`}>
            <div className="flex items-start justify-between gap-3">
              <p className="font-bold text-slate-900">{i + 1}. {f.body}</p>
              <span className={`badge shrink-0 ${f.is_correct ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'}`}>
                {f.is_correct ? '✓ صحيحة' : '✗ خاطئة'}
              </span>
            </div>
            {f.explanation && (
              <p className="mt-2 rounded-xl bg-brand-50/60 p-3 text-sm leading-relaxed text-slate-600">
                💡 {f.explanation}
              </p>
            )}
          </div>
        ))}
      </section>
    );
  }

  // ---------------------------------------------------------- taking view
  return (
    <section>
      <PageHeader
        title="اختبار"
        crumbs={[{ label: 'اختبار' }]}
        actions={remaining !== null ? (
          <span className={`badge text-base ${remaining < 60 ? 'bg-red-50 text-red-600' : ''}`} dir="ltr">
            ⏱ {Math.floor(remaining / 60)}:{String(remaining % 60).padStart(2, '0')}
          </span>
        ) : undefined}
      />

      {!questions.length ? (
        <p className="label">{t('common.loading')}</p>
      ) : (
        <>
          {questions.map((q, i) => (
            <div key={q.id} className="card">
              <div className="mb-3 flex items-start justify-between gap-3">
                <p className="font-bold text-slate-900">{i + 1}. {q.body}</p>
                <span className="badge shrink-0">{q.points} نقطة</span>
              </div>

              {q.type === 'mcq' && q.choices && (
                <div className="space-y-2">
                  {q.choices.map((c) => {
                    const picked = Array.isArray(answers[q.id]) && (answers[q.id] as string[]).includes(c.id);
                    return (
                      <label key={c.id}
                        className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm transition ${
                          picked ? 'border-brand-400 bg-brand-50' : 'border-slate-200 hover:border-slate-300'
                        }`}>
                        <input type="checkbox" checked={picked}
                          onChange={(e) => setAnswers((prev) => {
                            const cur = Array.isArray(prev[q.id]) ? (prev[q.id] as string[]) : [];
                            return { ...prev, [q.id]: e.target.checked ? [...cur, c.id] : cur.filter((x) => x !== c.id) };
                          })} />
                        {c.text}
                      </label>
                    );
                  })}
                </div>
              )}

              {q.type === 'true_false' && (
                <div className="flex gap-2">
                  {[true, false].map((v) => (
                    <button key={String(v)} type="button"
                      className={`chip ${answers[q.id] === v ? 'chip-active' : ''}`}
                      onClick={() => setAnswers((prev) => ({ ...prev, [q.id]: v }))}>
                      {v ? 'صح' : 'خطأ'}
                    </button>
                  ))}
                </div>
              )}

              {q.type === 'short_answer' && (
                <input className="input m-0" placeholder="إجابتك…"
                  value={(answers[q.id] as string) ?? ''}
                  onChange={(e) => setAnswers((prev) => ({ ...prev, [q.id]: e.target.value }))} />
              )}
            </div>
          ))}

          <div className="flex items-center gap-3">
            <button className="btn" disabled={busy} onClick={() => void submit()}>
              {busy ? t('common.loading') : 'تسليم الإجابات'}
            </button>
            {err && <span className="error">{err}</span>}
          </div>
        </>
      )}
    </section>
  );
}
