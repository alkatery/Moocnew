'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Course } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';

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

  return (
    <section>
      <div className="mb-5 overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-700 to-brand-500 p-8 text-white shadow-card">
        <span className="badge bg-white/15 text-white">
          {course.pricing_type === 'free' ? t('course.free') : formatMinor(course.price_minor)}
        </span>
        <h1 className="mt-3 text-3xl font-extrabold text-white">{course.title}</h1>
        {course.instructor?.name && <p className="mt-1 text-brand-100">{course.instructor.name}</p>}
        <p className="mt-3 max-w-2xl text-brand-50/90">{course.description ?? course.summary}</p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <div className="md:col-span-2">
          <h2 className="mb-3">المحتوى</h2>
          {course.sections?.map((s) => (
            <div key={s.id} className="card">
              <strong className="text-slate-900">{s.title}</strong>
              <ul className="mt-2 space-y-1">
                {s.lessons.map((l) => (
                  <li key={l.id} className="flex items-center gap-2 text-sm text-slate-600">
                    <span className="text-brand-500">▸</span>{l.title}
                    {l.is_free_preview && <span className="badge">معاينة</span>}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <aside>
          <div className="card sticky top-20">
            {msg && <p className="error mb-3">{msg}</p>}
            <div className="page-actions flex-col">
              <button className="btn w-full" onClick={() => void enroll()} disabled={busy}>{t('course.enroll')}</button>
              {course.pricing_type !== 'free' && (
                <Link className="btn btn-ghost w-full" href={`/checkout/${course.slug}`}>{t('course.buy')}</Link>
              )}
              <Link className="btn btn-ghost w-full" href={`/community/${course.slug}`}>{t('community.title')}</Link>
            </div>
          </div>
        </aside>
      </div>
    </section>
  );
}
