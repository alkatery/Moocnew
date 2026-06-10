'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { Paginated, PathSummary } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';

export default function PathsPage() {
  const [paths, setPaths] = useState<PathSummary[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api<Paginated<PathSummary>>('/learning/paths', { auth: false })
      .then((r) => setPaths(r.data)).catch(() => setPaths([])).finally(() => setLoading(false));
  }, []);

  return (
    <section>
      <PageHeader
        title={t('paths.title')}
        subtitle="حِزم دورات مرتّبة على مستويات تأخذها بالتسلسل، وتنتهي بشهادة مسار موثّقة."
        crumbs={[{ label: t('paths.title') }]}
      />

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : paths.length === 0 ? (
        <EmptyState text="لا توجد مسارات منشورة بعد." />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {paths.map((p, i) => (
            <Link key={p.id} href={`/paths/${p.slug}`}
              className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card transition hover:-translate-y-0.5 hover:shadow-lg">
              <div className={`relative h-24 bg-gradient-to-bl ${['from-brand-700 to-brand-500', 'from-indigo-700 to-sky-500', 'from-teal-700 to-emerald-500'][i % 3]}`}>
                <svg className="absolute -bottom-3 end-4 opacity-25" width="64" height="64" viewBox="0 0 24 24" fill="none" aria-hidden>
                  <path d="M4 6h6v6H4zM14 12h6v6h-6zM10 9h4m-2 0v6" stroke="#fff" strokeWidth="1.6" />
                </svg>
                <span className="absolute bottom-2.5 start-3 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur">
                  مسار تخصصي
                </span>
              </div>
              <div className="flex flex-1 flex-col p-4">
                <strong className="line-clamp-1 text-slate-900">{p.title}</strong>
                <p className="mt-1 line-clamp-2 text-sm text-slate-500">{p.summary}</p>
                <div className="mt-auto flex items-center gap-2 pt-3 text-xs text-slate-400">
                  <span className="badge">{p.levels_count} مستويات</span>
                  <span className="badge">{p.courses_count} دورات</span>
                  <span className="badge bg-emerald-50 text-emerald-700">شهادة مسار</span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}
