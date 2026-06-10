'use client';

import { useCallback, useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { t } from '@/i18n/dictionary';

interface Note { id: number; at_seconds: number | null; body: string }

interface CheckpointQuestion {
  id: number;
  type: 'mcq' | 'true_false' | 'short_answer';
  body: string;
  choices: { id: string; text: string }[] | null;
}

interface Checkpoint { at_seconds: number; question: CheckpointQuestion }

function fmt(s: number): string {
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/**
 * Learner-side video interaction: private time-anchored notes (click →
 * seek) and in-video formative checkpoints with instant feedback.
 * `getTime`/`seekTo` are wired when the player is a native <video>;
 * embedded iframes fall back to manual timestamps and a question list.
 */
export function LessonInteraction({
  lessonId,
  getTime,
  seekTo,
}: {
  lessonId: number;
  getTime?: () => number | null;
  seekTo?: (seconds: number) => void;
}) {
  const [notes, setNotes] = useState<Note[]>([]);
  const [checkpoints, setCheckpoints] = useState<Checkpoint[]>([]);
  const [draft, setDraft] = useState('');
  const [err, setErr] = useState('');

  const load = useCallback(() => {
    api<{ data: Note[] }>(`/lessons/${lessonId}/notes`).then((r) => setNotes(r.data)).catch(() => undefined);
    api<{ data: Checkpoint[] }>(`/lessons/${lessonId}/checkpoints`).then((r) => setCheckpoints(r.data)).catch(() => setCheckpoints([]));
  }, [lessonId]);
  useEffect(load, [load]);

  async function addNote() {
    if (!draft.trim()) return;
    setErr('');
    const at = getTime?.() ?? null;
    try {
      await api(`/lessons/${lessonId}/notes`, {
        method: 'POST',
        body: { body: draft.trim(), at_seconds: at !== null ? Math.floor(at) : null },
      });
      setDraft('');
      load();
    } catch { setErr(t('common.error')); }
  }

  return (
    <div className="mt-4 grid gap-4 lg:grid-cols-2">
      {/* ----------------------------------------------------- notes */}
      <div className="card mb-0 self-start">
        <strong className="text-slate-900">🗒 ملاحظاتي على الدرس</strong>
        <p className="mb-3 text-xs text-slate-400">خاصة بك — تُحفظ مع توقيت الفيديو الحالي للعودة إليها بنقرة.</p>
        <div className="mb-3 flex gap-2">
          <input className="input m-0 flex-1" placeholder="اكتب ملاحظة…" value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') void addNote(); }} />
          <button className="btn shrink-0" onClick={() => void addNote()}>حفظ</button>
        </div>
        {err && <p className="error mb-2 text-sm">{err}</p>}
        {notes.length === 0 ? (
          <p className="text-sm text-slate-400">لا ملاحظات بعد.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {notes.map((n) => (
              <li key={n.id} className="flex items-start gap-2 py-2.5 text-sm">
                {n.at_seconds !== null && (
                  <button className="badge shrink-0 hover:bg-brand-100" dir="ltr"
                    title="الانتقال لهذه اللحظة"
                    onClick={() => seekTo?.(n.at_seconds!)}>
                    ▶ {fmt(n.at_seconds)}
                  </button>
                )}
                <span className="min-w-0 flex-1 text-slate-700">{n.body}</span>
                <button className="shrink-0 text-xs text-red-400 hover:text-red-600" aria-label="حذف"
                  onClick={() => void api(`/lesson-notes/${n.id}`, { method: 'DELETE' }).then(load).catch(() => setErr(t('common.error')))}>
                  ✕
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {/* ----------------------------------------------- checkpoints */}
      {checkpoints.length > 0 && (
        <div className="card mb-0 self-start">
          <strong className="text-slate-900">🎯 أسئلة تفاعلية أثناء الدرس</strong>
          <p className="mb-3 text-xs text-slate-400">اختبر فهمك عند المحطات الزمنية — لا تؤثر على درجتك.</p>
          <ul className="space-y-3">
            {checkpoints.map((cp) => (
              <CheckpointItem key={cp.question.id} lessonId={lessonId} cp={cp} seekTo={seekTo} />
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

function CheckpointItem({ lessonId, cp, seekTo }: { lessonId: number; cp: Checkpoint; seekTo?: (s: number) => void }) {
  const [answer, setAnswer] = useState<unknown>(null);
  const [result, setResult] = useState<{ correct: boolean; explanation: string | null } | null>(null);
  const [busy, setBusy] = useState(false);

  async function check() {
    if (answer === null || answer === '') return;
    setBusy(true);
    try {
      const res = await api<{ correct: boolean; explanation: string | null }>(
        `/lessons/${lessonId}/checkpoints/${cp.question.id}/answer`,
        { method: 'POST', body: { answer } },
      );
      setResult(res);
    } catch { /* keep silent; learner can retry */ } finally { setBusy(false); }
  }

  return (
    <li className="rounded-xl border border-slate-200 p-3">
      <div className="mb-2 flex items-start justify-between gap-2">
        <p className="text-sm font-bold text-slate-800">{cp.question.body}</p>
        <button className="badge shrink-0 hover:bg-brand-100" dir="ltr" onClick={() => seekTo?.(cp.at_seconds)}>
          ▶ {fmt(cp.at_seconds)}
        </button>
      </div>

      {cp.question.type === 'true_false' && (
        <div className="flex gap-2">
          {[true, false].map((v) => (
            <button key={String(v)} className={`chip ${answer === v ? 'chip-active' : ''}`}
              onClick={() => { setAnswer(v); setResult(null); }}>
              {v ? 'صح' : 'خطأ'}
            </button>
          ))}
        </div>
      )}
      {cp.question.type === 'mcq' && cp.question.choices && (
        <div className="space-y-1">
          {cp.question.choices.map((c) => {
            const picked = Array.isArray(answer) && (answer as string[]).includes(c.id);
            return (
              <label key={c.id} className="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" checked={picked}
                  onChange={(e) => {
                    setResult(null);
                    setAnswer((prev: unknown) => {
                      const cur = Array.isArray(prev) ? (prev as string[]) : [];
                      return e.target.checked ? [...cur, c.id] : cur.filter((x) => x !== c.id);
                    });
                  }} />
                {c.text}
              </label>
            );
          })}
        </div>
      )}
      {cp.question.type === 'short_answer' && (
        <input className="input m-0" placeholder="إجابتك…" value={(answer as string) ?? ''}
          onChange={(e) => { setAnswer(e.target.value); setResult(null); }} />
      )}

      <div className="mt-2 flex items-center gap-2">
        <button className="btn btn-ghost text-xs" disabled={busy} onClick={() => void check()}>تحقق</button>
        {result && (
          <span className={`text-sm font-bold ${result.correct ? 'text-emerald-600' : 'text-red-500'}`}>
            {result.correct ? '✓ إجابة صحيحة' : '✗ غير صحيحة'}
          </span>
        )}
      </div>
      {result?.explanation && (
        <p className="mt-2 rounded-lg bg-brand-50/60 p-2 text-xs leading-relaxed text-slate-600">💡 {result.explanation}</p>
      )}
    </li>
  );
}
