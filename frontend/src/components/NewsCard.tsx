import Link from 'next/link';
import type { NewsPost } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';

export function NewsCard({ post }: { post: NewsPost }) {
  return (
    <article className="card mb-0 flex flex-col transition hover:-translate-y-0.5 hover:shadow-lg">
      <time className="badge self-start" dateTime={post.published_at ?? undefined}>
        {formatDate(post.published_at)}
      </time>
      <h3 className="mt-3 line-clamp-2 text-base font-bold text-slate-900">
        <Link className="text-slate-900 hover:text-brand-700" href={`/news/${post.slug}`}>{post.title}</Link>
      </h3>
      <p className="mt-2 line-clamp-3 flex-1 text-sm leading-6 text-slate-500">{post.excerpt ?? post.body}</p>
      <Link className="mt-3 inline-flex items-center gap-1 text-sm font-semibold" href={`/news/${post.slug}`}>
        {t('news.readMore')}
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" className="rtl:rotate-180" aria-hidden>
          <path d="M5 12h14m0 0-6-6m6 6-6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </Link>
    </article>
  );
}
