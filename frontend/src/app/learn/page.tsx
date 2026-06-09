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
    if (!user) {
      setLoading(false);
      return;
    }
    api<Paginated<Enrollment>>('/enrollments')
      .then((res) => setItems(res.data))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [user, authLoading]);

  if (authLoading || loading) return <p className="label">{t('common.loading')}</p>;
  if (!user) return <p className="label"><Link href="/login">{t('nav.login')}</Link></p>;

  return (
    <section>
      <h1>{t('learn.title')}</h1>
      {items.length === 0 ? (
        <p className="label">{t('catalog.empty')}</p>
      ) : (
        items.map((e) => (
          <Link key={e.id} href={e.course ? `/learn/${e.course.slug}` : '#'} className="card" style={{ display: 'block' }}>
            <strong>{e.course?.title ?? `#${e.course_id}`}</strong>
            <div className="label">{t('learn.progress')}: {e.progress_percent}%</div>
            <div className="progress"><span style={{ width: `${e.progress_percent}%` }} /></div>
          </Link>
        ))
      )}
    </section>
  );
}
