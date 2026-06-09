'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import type { Course, Paginated } from '@/lib/types';
import { t } from '@/i18n/dictionary';

export default function CatalogPage() {
  const [courses, setCourses] = useState<Course[]>([]);
  const [q, setQ] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    const query = q ? `?q=${encodeURIComponent(q)}` : '';
    api<Paginated<Course>>(`/catalog/courses${query}`, { auth: false })
      .then((res) => setCourses(res.data))
      .catch(() => setCourses([]))
      .finally(() => setLoading(false));
    return () => controller.abort();
  }, [q]);

  return (
    <section>
      <h1>{t('catalog.title')}</h1>
      <input className="input" placeholder={t('catalog.search')} value={q} onChange={(e) => setQ(e.target.value)} />
      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : courses.length === 0 ? (
        <p className="label">{t('catalog.empty')}</p>
      ) : (
        courses.map((c) => (
          <Link key={c.id} href={`/catalog/${c.slug}`} className="card" style={{ display: 'block' }}>
            <strong>{c.title}</strong>
            <span className="badge" style={{ marginInlineStart: 8 }}>
              {c.pricing_type === 'free' ? t('course.free') : `${(c.price_minor / 100).toFixed(2)}`}
            </span>
            <p className="label">{c.summary}</p>
          </Link>
        ))
      )}
    </section>
  );
}
