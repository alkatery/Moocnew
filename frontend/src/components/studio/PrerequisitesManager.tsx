'use client';

// E1 — تأليف: إدارة المتطلّبات السابقة للمقرر (إضافة/إزالة).
// المتطلّب مقرر منشور؛ يُختار من مقررات المؤلّف المنشورة (GET /catalog/mine).

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { Course, CoursePrerequisite, Paginated } from '@/lib/types';
import { ErrorMsg } from '@/components/StatusMessage';
import { t } from '@/i18n/dictionary';

export function PrerequisitesManager({
  courseSlug,
  courseId,
  initial,
}: {
  courseSlug: string;
  courseId: number;
  initial: CoursePrerequisite[];
}) {
  const [prereqs, setPrereqs] = useState<CoursePrerequisite[]>(initial);
  const [candidates, setCandidates] = useState<Course[]>([]);
  const [selected, setSelected] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  // مقررات المؤلّف المنشورة = مرشّحات المتطلّبات (عدا هذا المقرر).
  useEffect(() => {
    api<Paginated<Course>>('/catalog/mine')
      .then((r) => setCandidates(r.data.filter((c) => c.id !== courseId && c.status === 'published')))
      .catch(() => setError(t('prereq.loadError')));
  }, [courseId]);

  // المرشّحات غير المُضافة بعد.
  const available = candidates.filter((c) => !prereqs.some((p) => p.id === c.id));

  async function add() {
    if (!selected) return;
    setBusy(true);
    setError('');
    try {
      const res = await api<{ data: CoursePrerequisite[] }>(
        `/catalog/courses/${courseSlug}/prerequisites`,
        { method: 'POST', body: { prerequisite_course_id: Number(selected) } },
      );
      setPrereqs(res.data);
      setSelected('');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('prereq.addError'));
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    setError('');
    try {
      await api(`/catalog/courses/${courseSlug}/prerequisites/${id}`, { method: 'DELETE' });
      setPrereqs((prev) => prev.filter((p) => p.id !== id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('prereq.removeError'));
    }
  }

  return (
    <section className="card mb-4" aria-labelledby="prereq-heading">
      <h3 id="prereq-heading" className="text-base font-bold text-slate-900">{t('prereq.title')}</h3>
      <p className="mb-3 mt-1 text-xs text-slate-500">{t('prereq.studioHint')}</p>

      <ErrorMsg msg={error} />

      {prereqs.length === 0 ? (
        <p className="mb-3 text-sm text-slate-500">{t('prereq.none')}</p>
      ) : (
        <ul className="mb-3 divide-y divide-slate-100">
          {prereqs.map((p) => (
            <li key={p.id} className="flex items-center justify-between gap-2 py-2">
              <span className="min-w-0 truncate text-sm text-slate-700">{p.title}</span>
              <button
                type="button"
                className="shrink-0 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                onClick={() => void remove(p.id)}
                aria-label={`${t('prereq.remove')}: ${p.title}`}
              >
                {t('prereq.remove')}
              </button>
            </li>
          ))}
        </ul>
      )}

      <label className="label" htmlFor="prereq-select">{t('prereq.add')}</label>
      <div className="flex gap-2">
        <select
          id="prereq-select"
          className="input m-0 flex-1"
          value={selected}
          onChange={(e) => setSelected(e.target.value)}
        >
          <option value="">{t('prereq.selectPlaceholder')}</option>
          {available.map((c) => (
            <option key={c.id} value={c.id}>{c.title}</option>
          ))}
        </select>
        <button type="button" className="btn shrink-0" disabled={busy || !selected} onClick={() => void add()}>
          {busy ? t('common.loading') : t('prereq.add')}
        </button>
      </div>
    </section>
  );
}
