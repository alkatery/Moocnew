'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Review, ReviewSummary } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { Stars } from '@/components/Stars';

export function Reviews({ courseSlug }: { courseSlug: string }) {
  const { user } = useAuth();
  const [reviews, setReviews] = useState<Review[]>([]);
  const [summary, setSummary] = useState<ReviewSummary | null>(null);
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  function load() {
    api<{ data: Review[]; summary: ReviewSummary }>(`/catalog/courses/${courseSlug}/reviews`, { auth: false })
      .then((r) => { setReviews(r.data); setSummary(r.summary); })
      .catch(() => undefined);
  }
  useEffect(load, [courseSlug]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setError('');
    try {
      await api(`/catalog/courses/${courseSlug}/reviews`, { method: 'POST', body: { rating, comment: comment || undefined } });
      setComment('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="card">
      <div className="mb-4 flex items-center justify-between">
        <strong className="text-slate-900">{t('reviews.title')}</strong>
        {summary && summary.count > 0 && (
          <span className="flex items-center gap-2 text-sm text-slate-500">
            <Stars value={summary.average} />
            <span className="font-bold text-slate-700">{summary.average}</span>
            <span className="text-slate-400">({summary.count} {t('reviews.count')})</span>
          </span>
        )}
      </div>

      {user && (
        <form className="mb-4 rounded-xl bg-slate-50 p-4" onSubmit={(e) => void submit(e)}>
          <div className="mb-2 flex items-center gap-3">
            <span className="text-sm text-slate-600">{t('reviews.yourRating')}:</span>
            <Stars value={rating} size={22} onChange={setRating} />
          </div>
          <textarea className="input min-h-20" placeholder={t('reviews.write')}
            value={comment} onChange={(e) => setComment(e.target.value)} />
          {error && <p className="error mb-2">{error}</p>}
          <button className="btn" disabled={busy}>{busy ? t('common.loading') : t('reviews.submit')}</button>
        </form>
      )}

      {reviews.length === 0 ? (
        <p className="text-sm text-slate-400">{t('reviews.empty')}</p>
      ) : (
        <ul className="divide-y divide-slate-100">
          {reviews.map((r) => (
            <li key={r.id} className="py-3">
              <div className="flex items-center justify-between">
                <strong className="text-sm text-slate-800">{r.user ?? '—'}</strong>
                <Stars value={r.rating} size={14} />
              </div>
              {r.comment && <p className="mt-1 text-sm leading-6 text-slate-600">{r.comment}</p>}
              {r.created_at && <time className="text-xs text-slate-400">{formatDate(r.created_at)}</time>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
