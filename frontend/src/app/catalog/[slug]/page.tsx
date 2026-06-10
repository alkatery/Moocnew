'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Course } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';

const PERKS = [
  'وصول كامل لكل دروس الدورة',
  'اختبارات وواجبات مع تصحيح',
  'شهادة إتمام موثّقة برمز QR',
  'مجتمع نقاش بإشراف المدرّب',
];

export default function CourseDetailPage() {
  const { slug } = useParams<{ slug: string }>();
  const { user } = useAuth();
  const router = useRouter();
  const [course, setCourse] = useState<Course | null>(null);
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((res) => setCourse(res.data)).catch(() => setCourse(null));
  }, [slug]);

  async function enroll() {
    if (!user) { router.push('/login'); return; }
    setBusy(true); setMsg('');
    try {
      await api(`/catalog/courses/${slug}/enroll`, { method: 'POST' });
      router.push(`/learn/${slug}`);
    } catch (err) {
      setMsg(err instanceof ApiError ? err.message : t('common.error'));
    } finally { setBusy(false); }
  }

  if (!course) return <p className="label">{t('common.loading')}</p>;

  const sectionsCount = course.sections?.length ?? 0;
  const lessonsCount = course.sections?.reduce((n, s) => n + s.lessons.length, 0) ?? 0;

  return (
    <section>
      <nav className="mb-4 flex flex-wrap items-center gap-1.5 text-xs text-slate-400" aria-label="مسار التنقّل">
        <Link className="text-slate-400 hover:text-brand-600" href="/">الرئيسية</Link>
        <span aria-hidden>‹</span>
        <Link className="text-slate-400 hover:text-brand-600" href="/catalog">{t('nav.catalog')}</Link>
        <span aria-hidden>‹</span>
        <span className="font-medium text-slate-500">{course.title}</span>
      </nav>

      {/* Hero */}
      <div className="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-900 via-brand-700 to-brand-500 p-8 text-white shadow-card sm:p-10">
        <div className="pointer-events-none absolute -bottom-24 -start-10 h-64 w-64 rounded-full bg-white/10 blur-3xl" aria-hidden />
        <div className="relative max-w-2xl">
          <div className="flex flex-wrap items-center gap-2">
            {course.category?.name && <span className="badge bg-white/15 text-white">{course.category.name}</span>}
            <span className="badge bg-white/15 text-white">
              {course.pricing_type === 'free' ? t('course.free') : formatMinor(course.price_minor)}
            </span>
          </div>
          <h1 className="mt-4 text-3xl font-extrabold leading-snug text-white sm:text-4xl">{course.title}</h1>
          <p className="mt-3 leading-8 text-brand-50/90">{course.description ?? course.summary}</p>

          <div className="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-brand-100">
            {course.instructor?.name && (
              <span className="flex items-center gap-2">
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-xs font-extrabold">
                  {course.instructor.name.slice(0, 1)}
                </span>
                {course.instructor.name}
              </span>
            )}
            <span>{sectionsCount} أقسام</span>
            <span>{lessonsCount} درساً</span>
            <span className="flex items-center gap-1">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden>
                <path d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-3 1.5L8 21l4-2 4 2-1-5.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
              شهادة إتمام
            </span>
          </div>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        {/* Curriculum */}
        <div className="md:col-span-2">
          <h2 className="mb-4 text-2xl font-extrabold">محتوى الدورة</h2>
          {course.sections?.length ? course.sections.map((s, si) => (
            <div key={s.id} className="card p-0">
              <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                <strong className="text-slate-900">القسم {si + 1}: {s.title}</strong>
                <span className="text-xs text-slate-400">{s.lessons.length} دروس</span>
              </div>
              <ul className="divide-y divide-slate-50">
                {s.lessons.map((l) => (
                  <li key={l.id} className="flex items-center gap-3 px-5 py-3 text-sm text-slate-600">
                    <span className="text-slate-400"><LessonTypeIcon type={l.type} /></span>
                    <span className="flex-1">{l.title}</span>
                    {l.is_free_preview && <span className="badge">معاينة مجانية</span>}
                  </li>
                ))}
              </ul>
            </div>
          )) : (
            <div className="card text-slate-500">سيُنشر المنهج التفصيلي قريباً.</div>
          )}
        </div>

        {/* Sticky enrollment card */}
        <aside>
          <div className="card sticky top-20">
            <div className="mb-4 text-center">
              <div className="text-3xl font-extrabold text-brand-700">
                {course.pricing_type === 'free' ? t('course.free') : formatMinor(course.price_minor)}
              </div>
              {course.pricing_type !== 'free' && <p className="mt-1 text-xs text-slate-400">دفعة واحدة — وصول دائم</p>}
            </div>

            {msg && <p className="error mb-3">{msg}</p>}

            <div className="page-actions flex-col">
              <button className="btn w-full" onClick={() => void enroll()} disabled={busy}>
                {busy ? t('common.loading') : t('course.enroll')}
              </button>
              {course.pricing_type !== 'free' && (
                <Link className="btn btn-ghost w-full" href={`/checkout/${course.slug}`}>{t('course.buy')}</Link>
              )}
              <Link className="btn btn-ghost w-full" href={`/community/${course.slug}`}>{t('community.title')}</Link>
            </div>

            <ul className="mt-5 space-y-2.5 border-t border-slate-100 pt-4 text-sm text-slate-600">
              {PERKS.map((perk) => (
                <li key={perk} className="flex items-center gap-2">
                  <svg className="shrink-0 text-emerald-500" width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="m5 13 4 4 10-10" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                  {perk}
                </li>
              ))}
            </ul>
          </div>
        </aside>
      </div>
    </section>
  );
}
