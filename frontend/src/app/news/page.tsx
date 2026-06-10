'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { NewsPost, Paginated } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { NewsCard } from '@/components/NewsCard';

export default function NewsPage() {
  const [posts, setPosts] = useState<NewsPost[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api<Paginated<NewsPost>>(`/content/news?per_page=9&page=${page}`, { auth: false })
      .then((res) => {
        setPosts((prev) => (page === 1 ? res.data : [...prev, ...res.data]));
        setLastPage(res.meta?.last_page ?? 1);
      })
      .catch(() => undefined)
      .finally(() => setLoading(false));
  }, [page]);

  return (
    <section>
      <div className="mb-6">
        <h1 className="text-3xl font-extrabold">{t('news.title')}</h1>
        <p className="mt-2 text-slate-500">جديد المنصة: إعلانات الدورات والشراكات والتحديثات.</p>
      </div>

      {loading && posts.length === 0 ? (
        <p className="label">{t('common.loading')}</p>
      ) : posts.length === 0 ? (
        <div className="card text-center text-slate-500">{t('news.empty')}</div>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {posts.map((p) => <NewsCard key={p.id} post={p} />)}
          </div>
          {page < lastPage && (
            <div className="mt-8 text-center">
              <button className="btn btn-ghost" onClick={() => setPage((p) => p + 1)} disabled={loading}>
                {loading ? t('common.loading') : t('news.loadMore')}
              </button>
            </div>
          )}
        </>
      )}
    </section>
  );
}
