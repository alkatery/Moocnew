'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import type { Course, Paginated } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';

export default function CatalogPage() {
  const [courses, setCourses] = useState<Course[]>([]);
  const [q, setQ] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    const query = q ? `?q=${encodeURIComponent(q)}` : '';
    api<Paginated<Course>>(`/catalog/courses${query}`, { auth: false })
      .then((res) => setCourses(res.data))
      .catch(() => setCourses([]))
      .finally(() => setLoading(false));
  }, [q]);

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>{t('catalog.title')}</h1>
        <input className="input m-0 w-72 max-w-full" placeholder={t('catalog.search')} value={q} onChange={(e) => setQ(e.target.value)} />
      </div>
      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : courses.length === 0 ? (
        <p className="label">{t('catalog.empty')}</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {courses.map((c, i) => (
            <Link key={c.id} href={`/catalog/${c.slug}`}
              className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card transition hover:-translate-y-0.5 hover:shadow-lg">
              <div className={`h-28 bg-gradient-to-bl ${['from-brand-500 to-brand-700','from-sky-500 to-indigo-700','from-emerald-500 to-teal-700'][i % 3]}`} />
              <div className="p-4">
                <div className="mb-1 flex items-center justify-between gap-2">
                  <strong className="line-clamp-1 text-slate-900">{c.title}</strong>
                  <span className="badge shrink-0">{c.pricing_type === 'free' ? t('course.free') : formatMinor(c.price_minor)}</span>
                </div>
                <p className="line-clamp-2 text-sm text-slate-500">{c.summary}</p>
              </div>
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}
