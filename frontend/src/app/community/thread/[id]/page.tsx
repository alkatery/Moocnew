'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { ForumPost } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';

export default function ThreadPage() {
  const { id } = useParams<{ id: string }>();
  const [title, setTitle] = useState('');
  const [posts, setPosts] = useState<ForumPost[]>([]);
  const [body, setBody] = useState('');

  function load() {
    api<{ data: { thread: { title: string }; posts: ForumPost[] } }>(`/community/threads/${id}`)
      .then((r) => { setTitle(r.data.thread.title); setPosts(r.data.posts); })
      .catch(() => setPosts([]));
  }
  useEffect(load, [id]);

  async function reply(e: React.FormEvent) {
    e.preventDefault();
    await api(`/community/threads/${id}/posts`, { method: 'POST', body: { body } });
    setBody(''); load();
  }

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader
        title={title || t('common.loading')}
        crumbs={[{ label: t('community.title') }]}
      />

      <div className="space-y-3">
        {posts.map((p, i) => (
          <div key={p.id} className="card mb-0 flex gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 font-extrabold text-brand-700">
              {i === 0 ? 'س' : 'ر'}
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2 text-xs text-slate-400">
                <span className="font-bold text-slate-600">{i === 0 ? 'صاحب الموضوع' : `مشارك`}</span>
                {p.created_at && <time>{formatDate(p.created_at)}</time>}
              </div>
              <p className="mt-1.5 whitespace-pre-line text-sm leading-7 text-slate-700">{p.body}</p>
            </div>
          </div>
        ))}
      </div>

      <form className="card mt-5" onSubmit={reply}>
        <label className="label" htmlFor="reply-body">{t('community.reply')}</label>
        <textarea id="reply-body" className="input min-h-24"
          value={body} onChange={(e) => setBody(e.target.value)} required />
        <button className="btn">{t('community.send')}</button>
      </form>
    </section>
  );
}
