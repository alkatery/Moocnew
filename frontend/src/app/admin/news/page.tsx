'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { NewsPost, Paginated } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { ErrorMsg } from '@/components/StatusMessage';

const EMPTY = { title: '', excerpt: '', body: '', published: true };

export default function AdminNewsPage() {
  const [posts, setPosts] = useState<NewsPost[]>([]);
  const [form, setForm] = useState(EMPTY);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function load() {
    try {
      const res = await api<Paginated<NewsPost>>('/content/news?include_drafts=1&per_page=24');
      setPosts(res.data);
    } catch {
      setError(t('common.error'));
    }
  }

  useEffect(() => { void load(); }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api('/content/news', {
        method: 'POST',
        body: { ...form, excerpt: form.excerpt || undefined },
      });
      setForm(EMPTY);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  async function togglePublish(post: NewsPost) {
    try {
      await api(`/content/news/${post.slug}`, {
        method: 'PATCH',
        body: { published: !post.published_at },
      });
      await load();
    } catch { setError(t('common.error')); }
  }

  async function remove(post: NewsPost) {
    try {
      await api(`/content/news/${post.slug}`, { method: 'DELETE' });
      setPosts((prev) => prev.filter((p) => p.id !== post.id));
    } catch { setError(t('common.error')); }
  }

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>{t('admin.news')}</h1>
        <Link className="btn btn-ghost" href="/admin">{t('admin.title')}</Link>
      </div>
      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={error} />

      <div className="grid gap-6 lg:grid-cols-2">
        <form className="card mb-0 self-start" onSubmit={(e) => void create(e)}>
          <strong className="text-slate-900">خبر جديد</strong>
          <label className="label mt-3 block" htmlFor="np-title">{t('common.title')}</label>
          <input id="np-title" className="input" required maxLength={255}
            value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          <label className="label" htmlFor="np-excerpt">مقتطف (يظهر في البطاقات)</label>
          <input id="np-excerpt" className="input" maxLength={500}
            value={form.excerpt} onChange={(e) => setForm({ ...form, excerpt: e.target.value })} />
          <label className="label" htmlFor="np-body">{t('common.body')}</label>
          <textarea id="np-body" className="input min-h-36" required
            value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} />
          <label className="mb-3 flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" checked={form.published}
              onChange={(e) => setForm({ ...form, published: e.target.checked })} />
            نشر فوراً
          </label>
          <button className="btn w-full" type="submit" disabled={busy}>
            {busy ? t('common.loading') : t('studio.create')}
          </button>
        </form>

        <div>
          {posts.length === 0 ? (
            <div className="card text-center text-slate-500">{t('news.empty')}</div>
          ) : (
            posts.map((p) => (
              <div key={p.id} className="card">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <strong className="text-slate-900">{p.title}</strong>
                    <div className="mt-1 flex items-center gap-2 text-xs text-slate-500">
                      <span className={`badge ${p.published_at ? '' : 'bg-amber-50 text-amber-700'}`}>
                        {p.published_at ? 'منشور' : 'مسودة'}
                      </span>
                      {p.published_at && <time>{formatDate(p.published_at)}</time>}
                    </div>
                  </div>
                  <div className="page-actions">
                    <button className="btn btn-ghost" onClick={() => void togglePublish(p)}>
                      {p.published_at ? 'إلغاء النشر' : 'نشر'}
                    </button>
                    <button className="btn bg-red-600 hover:bg-red-700" onClick={() => void remove(p)}>حذف</button>
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </section>
  );
}
