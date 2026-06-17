'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { ForumThreadSummary } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';

export default function CourseForumPage() {
  const { slug } = useParams<{ slug: string }>();
  const [threads, setThreads] = useState<ForumThreadSummary[]>([]);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(true);

  function load() {
    api<{ data: { data: ForumThreadSummary[] } }>(`/community/courses/${slug}/threads`)
      .then((r) => setThreads(r.data.data ?? []))
      .catch(() => setThreads([]))
      .finally(() => setLoading(false));
  }
  useEffect(load, [slug]);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    await api(`/community/courses/${slug}/threads`, { method: 'POST', body: { title, body } });
    setTitle(''); setBody(''); load();
  }

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader
        title={t('community.title')}
        subtitle="اسأل، شارك خبرتك، وتعلّم مع زملائك في الدورة."
        crumbs={[{ href: `/catalog/${slug}`, label: 'الدورة' }, { label: t('community.title') }]}
      />

      <form className="card" onSubmit={create}>
        <strong className="text-slate-900">{t('community.newThread')}</strong>
        <label className="label mt-3 block" htmlFor="th-title">{t('common.title')}</label>
        <input id="th-title" className="input" value={title} onChange={(e) => setTitle(e.target.value)} required />
        <label className="label" htmlFor="th-body">{t('common.body')}</label>
        <textarea id="th-body" className="input min-h-24" value={body} onChange={(e) => setBody(e.target.value)} required />
        <button className="btn">{t('community.send')}</button>
      </form>

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : threads.length === 0 ? (
        <EmptyState text="لا توجد مواضيع بعد — كن أول من يبدأ النقاش." />
      ) : (
        <div className="card p-0">
          <ul className="divide-y divide-slate-100">
            {threads.map((th) => (
              <li key={th.id}>
                <Link href={`/community/thread/${th.id}`}
                  className="flex items-center gap-3 px-5 py-4 transition hover:bg-brand-50/50">
                  <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 font-extrabold text-brand-700">
                    {th.title.slice(0, 1)}
                  </span>
                  <span className="flex-1 font-bold text-slate-800">{th.title}</span>
                  <span className="badge">{th.posts_count ?? 0} ردود</span>
                  <svg className="text-slate-300 rtl:rotate-180" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="m9 6 6 6-6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
