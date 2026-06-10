'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { NewsPost } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';

export default function NewsDetailPage() {
  const { slug } = useParams<{ slug: string }>();
  const [post, setPost] = useState<NewsPost | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    api<{ data: NewsPost }>(`/content/news/${slug}`, { auth: false })
      .then((res) => setPost(res.data))
      .catch(() => setMissing(true));
  }, [slug]);

  if (missing) {
    return (
      <div className="card text-center">
        <p className="text-slate-500">{t('news.empty')}</p>
        <Link className="btn btn-ghost mt-4" href="/news">{t('news.back')}</Link>
      </div>
    );
  }

  if (!post) return <p className="label">{t('common.loading')}</p>;

  return (
    <article className="mx-auto max-w-3xl">
      <Link className="text-sm font-semibold" href="/news">‹ {t('news.back')}</Link>

      <header className="mt-4 overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-700 to-brand-500 p-8 text-white shadow-card">
        <time className="badge bg-white/15 text-white" dateTime={post.published_at ?? undefined}>
          {formatDate(post.published_at)}
        </time>
        <h1 className="mt-3 text-3xl font-extrabold leading-snug text-white">{post.title}</h1>
        {post.author?.name && <p className="mt-2 text-sm text-brand-100">بقلم {post.author.name}</p>}
      </header>

      <div className="card mt-5 whitespace-pre-line text-base leading-9 text-slate-700">
        {post.body}
      </div>
    </article>
  );
}
