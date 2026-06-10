'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Enrollment, Paginated } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { badgeTone, statusLabel } from '@/lib/labels';

export default function MyLearningPage() {
  const { user, loading: authLoading } = useAuth();
  const [items, setItems] = useState<Enrollment[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (authLoading) return;
    if (!user) { setLoading(false); return; }
    api<Paginated<Enrollment>>('/enrollments')
      .then((res) => setItems(res.data)).catch(() => setItems([])).finally(() => setLoading(false));
  }, [user, authLoading]);

  if (authLoading || loading) return <p className="label">{t('common.loading')}</p>;
  if (!user) {
    return <EmptyState text="سجّل دخولك لمتابعة دوراتك." action={<Link className="btn" href="/login">{t('nav.login')}</Link>} />;
  }

  return (
    <section>
      <PageHeader
        title={t('learn.title')}
        subtitle="تابع تقدّمك وأكمل من حيث توقفت."
        crumbs={[{ label: t('learn.title') }]}
        actions={<Link className="btn btn-ghost" href="/catalog">{t('nav.catalog')}</Link>}
      />

      {items.length === 0 ? (
        <EmptyState
          text="لم تلتحق بأي دورة بعد — ابدأ من المكتبة المفتوحة."
          action={<Link className="btn" href="/catalog?pricing=free">تصفّح الدورات المجانية</Link>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((e) => (
            <Link key={e.id} href={e.course ? `/learn/${e.course.slug}` : '#'}
              className="card mb-0 flex flex-col transition hover:-translate-y-0.5 hover:shadow-lg">
              <div className="mb-2 flex items-start justify-between gap-2">
                <strong className="line-clamp-2 text-slate-900">{e.course?.title ?? `#${e.course_id}`}</strong>
                <span className={`badge shrink-0 ${badgeTone(e.status)}`}>{statusLabel(e.status)}</span>
              </div>
              <div className="mt-auto pt-3">
                <div className="mb-1 flex items-center justify-between text-sm">
                  <span className="text-slate-500">{t('learn.progress')}</span>
                  <span className="font-bold text-brand-700">{e.progress_percent}%</span>
                </div>
                <div className="progress"><span style={{ width: `${e.progress_percent}%` }} /></div>
                <span className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">
                  متابعة التعلّم
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" className="rtl:rotate-180" aria-hidden>
                    <path d="M5 12h14m0 0-6-6m6 6-6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                </span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}
