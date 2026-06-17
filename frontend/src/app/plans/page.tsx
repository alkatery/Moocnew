'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { Course, Paginated, StudyPlanView } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorMsg } from '@/components/StatusMessage';

const CADENCES: { value: number; label: string }[] = [
  { value: 1, label: 'يومياً' },
  { value: 2, label: 'كل يومين' },
  { value: 3, label: 'كل ٣ أيام' },
  { value: 7, label: 'أسبوعياً' },
  { value: 14, label: 'كل أسبوعين' },
];

function cadenceLabel(days: number): string {
  return CADENCES.find((c) => c.value === days)?.label ?? `كل ${days} أيام`;
}

export default function PlansPage() {
  const [plans, setPlans] = useState<StudyPlanView[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  // Create form state.
  const [title, setTitle] = useState('');
  const [cadence, setCadence] = useState(3);
  const [targetDate, setTargetDate] = useState('');
  const [filter, setFilter] = useState('');
  const [selected, setSelected] = useState<number[]>([]);

  function load() {
    api<Paginated<StudyPlanView>>('/learning/study-plans')
      .then((r) => setPlans(r.data)).catch(() => setPlans([])).finally(() => setLoading(false));
  }

  useEffect(() => {
    load();
    void api<Paginated<Course>>('/catalog/courses?per_page=50', { auth: false })
      .then((r) => setCourses(r.data)).catch(() => undefined);
  }, []);

  const visibleCourses = useMemo(
    () => courses.filter((c) => !filter || c.title.includes(filter)),
    [courses, filter],
  );

  function toggleCourse(id: number) {
    setSelected((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
  }

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setError('');
    try {
      await api('/learning/study-plans', {
        method: 'POST',
        body: {
          title,
          cadence_days: cadence,
          target_date: targetDate || null,
          course_ids: selected,
        },
      });
      setTitle(''); setSelected([]); setTargetDate('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally { setBusy(false); }
  }

  async function remove(plan: StudyPlanView) {
    try {
      await api(`/learning/study-plans/${plan.id}`, { method: 'DELETE' });
      setPlans((prev) => prev.filter((p) => p.id !== plan.id));
    } catch { setError(t('common.error')); }
  }

  return (
    <section>
      <PageHeader
        title={t('plans.title')}
        subtitle="اجمع دوراتك في خطة، وحدّد وتيرة التذكير — وسنتابعك بالتنبيهات حتى تُنجزها."
        crumbs={[{ label: t('plans.title') }]}
      />
      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={error} />

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Create form */}
        <form className="card mb-0 self-start lg:order-2" onSubmit={(e) => void create(e)}>
          <strong className="text-slate-900">{t('plans.new')}</strong>

          <label className="label mt-3 block" htmlFor="plan-title">{t('common.title')}</label>
          <input id="plan-title" className="input" required maxLength={160}
            value={title} onChange={(e) => setTitle(e.target.value)} />

          <label className="label" htmlFor="plan-cadence">{t('plans.cadence')}</label>
          <select id="plan-cadence" className="input" value={cadence}
            onChange={(e) => setCadence(Number(e.target.value))}>
            {CADENCES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
          </select>

          <label className="label" htmlFor="plan-date">{t('plans.targetDate')}</label>
          <input id="plan-date" className="input" type="date" dir="ltr"
            value={targetDate} onChange={(e) => setTargetDate(e.target.value)} />

          <label className="label" htmlFor="plan-filter">{t('plans.pickCourses')} ({selected.length})</label>
          <input id="plan-filter" className="input" placeholder={t('catalog.search')}
            value={filter} onChange={(e) => setFilter(e.target.value)} />
          <div className="mb-4 max-h-56 overflow-y-auto rounded-xl border border-slate-200">
            {visibleCourses.length === 0 ? (
              <p className="p-3 text-sm text-slate-500">{t('catalog.empty')}</p>
            ) : visibleCourses.map((c) => (
              <label key={c.id} className="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2.5 text-sm last:border-0 hover:bg-brand-50/50">
                <input type="checkbox" className="accent-brand-600"
                  checked={selected.includes(c.id)} onChange={() => toggleCourse(c.id)} />
                <span className="line-clamp-1 text-slate-700">{c.title}</span>
              </label>
            ))}
          </div>

          <button className="btn w-full" disabled={busy || selected.length === 0}>
            {busy ? t('common.loading') : t('plans.create')}
          </button>
        </form>

        {/* My plans */}
        <div className="lg:col-span-2 lg:order-1">
          {loading ? (
            <p className="label">{t('common.loading')}</p>
          ) : plans.length === 0 ? (
            <EmptyState text={t('plans.empty')} />
          ) : (
            plans.map((plan) => (
              <div key={plan.id} className="card">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <strong className="text-lg text-slate-900">{plan.title}</strong>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                      <span className="badge">{cadenceLabel(plan.cadence_days)}</span>
                      {plan.target_date && <span>الهدف: {formatDate(plan.target_date)}</span>}
                      {plan.status === 'completed' && (
                        <span className="badge bg-emerald-50 text-emerald-700">مكتملة 🎉</span>
                      )}
                    </div>
                  </div>
                  <button className="btn btn-ghost text-red-600 ring-red-200 hover:bg-red-50"
                    onClick={() => void remove(plan)}>
                    {t('plans.delete')}
                  </button>
                </div>

                <div className="mt-4">
                  <div className="mb-1 flex items-center justify-between text-sm">
                    <span className="text-slate-500">{plan.progress.completed}/{plan.progress.total} دورات</span>
                    <span className="font-bold text-brand-700">{plan.progress.percent}%</span>
                  </div>
                  <div className="progress"><span style={{ width: `${plan.progress.percent}%` }} /></div>
                </div>

                <ul className="mt-4 space-y-2">
                  {plan.items.map((item) => (
                    <li key={item.course.id} className="flex items-center gap-2 text-sm">
                      {item.completed ? (
                        <svg className="shrink-0 text-emerald-500" width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden>
                          <path d="m5 13 4 4 10-10" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                      ) : (
                        <span className="h-2 w-2 shrink-0 rounded-full bg-slate-300" aria-hidden />
                      )}
                      <Link className={item.completed ? 'text-slate-500 line-through' : 'text-slate-700'}
                        href={`/catalog/${item.course.slug}`}>
                        {item.course.title}
                      </Link>
                    </li>
                  ))}
                </ul>

                {plan.next_course && plan.status === 'active' && (
                  <div className="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-brand-50 px-4 py-3">
                    <span className="text-sm text-slate-600">
                      {t('plans.next')}: <strong className="text-slate-900">{plan.next_course.title}</strong>
                    </span>
                    <Link className="btn" href={`/learn/${plan.next_course.slug}`}>متابعة</Link>
                  </div>
                )}
              </div>
            ))
          )}
        </div>
      </div>
    </section>
  );
}
