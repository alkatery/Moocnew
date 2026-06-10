'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { LeaderboardRow } from '@/lib/types';
import { formatCount } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';

const MEDALS = ['🥇', '🥈', '🥉'];

export default function LeaderboardPage() {
  const [rows, setRows] = useState<LeaderboardRow[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api<{ data: LeaderboardRow[] }>('/engagement/leaderboard', { auth: false })
      .then((r) => setRows(r.data)).catch(() => setRows([])).finally(() => setLoading(false));
  }, []);

  return (
    <section className="mx-auto max-w-2xl">
      <PageHeader
        title={t('leaderboard.title')}
        subtitle="أكثر المتعلّمين نشاطاً على المنصة بحسب النقاط."
        crumbs={[{ label: t('leaderboard.title') }]}
      />

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : rows.length === 0 ? (
        <EmptyState text={t('leaderboard.empty')} />
      ) : (
        <div className="card p-0">
          <ul className="divide-y divide-slate-100">
            {rows.map((r) => (
              <li key={r.rank} className="flex items-center gap-4 px-5 py-3.5">
                <span className="w-8 text-center text-lg font-extrabold text-slate-400">
                  {MEDALS[r.rank - 1] ?? r.rank}
                </span>
                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 font-extrabold text-brand-700">
                  {r.name.slice(0, 1)}
                </span>
                <span className="flex-1 font-bold text-slate-800">{r.name}</span>
                {r.current_streak > 0 && (
                  <span className="text-xs text-amber-600">🔥 {r.current_streak}</span>
                )}
                <strong className="text-brand-700">{formatCount(r.points)}</strong>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
