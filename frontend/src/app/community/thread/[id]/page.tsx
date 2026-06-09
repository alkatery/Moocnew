'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { ForumPost } from '@/lib/types';
import { t } from '@/i18n/dictionary';

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
    <section>
      <h1>{title}</h1>
      {posts.map((p) => <div key={p.id} className="card">{p.body}</div>)}
      <form className="card" onSubmit={reply}>
        <textarea className="input" placeholder={t('community.reply')} value={body} onChange={(e) => setBody(e.target.value)} required />
        <button className="btn">{t('community.send')}</button>
      </form>
    </section>
  );
}
