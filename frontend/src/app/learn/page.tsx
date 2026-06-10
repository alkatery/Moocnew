'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Enrollment, Paginated } from '@/lib/types';
import { t } from '@/i18n/dictionary';

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
  if (!user) return <p className="label"><Link href="/login">{t('nav.login')}</Link></p>;

  return (
    <section>
      <h1 className="mb-5">{t('learn.title')}</h1>
      {items.length === 0 ? (
        <p className="label">{t('catalog.empty')}</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {items.map((e) => (
            <Link key={e.id} href={e.course ? `/learn/${e.course.slug}` : '#'} className="card mb-0 transition hover:shadow-lg">
              <div className="mb-2 flex items-center justify-between">
                <strong className="text-slate-900">{e.course?.title ?? `#${e.course_id}`}</strong>
                <span className="badge">{e.status}</span>
              </div>
              <div className="mb-1 text-sm text-slate-500">{t('learn.progress')}: {e.progress_percent}%</div>
              <div className="progress"><span style={{ width: `${e.progress_percent}%` }} /></div>
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}
