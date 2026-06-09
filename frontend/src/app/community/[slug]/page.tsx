'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { ForumThreadSummary } from '@/lib/types';
import { t } from '@/i18n/dictionary';

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
    <section>
      <h1>{t('community.title')}</h1>
      <form className="card" onSubmit={create}>
        <strong>{t('community.newThread')}</strong>
        <input className="input" placeholder={t('common.title')} value={title} onChange={(e) => setTitle(e.target.value)} required />
        <textarea className="input" placeholder={t('common.body')} value={body} onChange={(e) => setBody(e.target.value)} required />
        <button className="btn">{t('community.send')}</button>
      </form>
      {loading ? <p className="label">{t('common.loading')}</p> : threads.map((th) => (
        <Link key={th.id} href={`/community/thread/${th.id}`} className="card" style={{ display: 'block' }}>
          <strong>{th.title}</strong> <span className="badge">{th.posts_count ?? 0}</span>
        </Link>
      ))}
    </section>
  );
}
