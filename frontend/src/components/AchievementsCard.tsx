'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { MyStats } from '@/lib/types';
import { t, type TranslationKey } from '@/i18n/dictionary';

export function badgeLabel(badge: string): string {
  return t(`badge.${badge}` as TranslationKey);
}

/** Compact gamification summary: points, streak, rank, badges. */
export function AchievementsCard() {
  const [stats, setStats] = useState<MyStats | null>(null);

  useEffect(() => {
    api<{ data: MyStats }>('/engagement/me').then((r) => setStats(r.data)).catch(() => setStats(null));
  }, []);

  if (!stats) return null;

  return (
    <div className="card">
      <div className="mb-3 flex items-center justify-between">
        <strong className="text-slate-900">{t('gam.title')}</strong>
        <Link className="text-xs font-semibold" href="/leaderboard">{t('nav.leaderboard')}</Link>
      </div>
      <div className="grid grid-cols-3 gap-2 text-center">
        <div className="rounded-xl bg-brand-50 py-3">
          <div className="text-2xl font-extrabold text-brand-700">{stats.points}</div>
          <div className="text-xs text-slate-500">{t('gam.points')}</div>
        </div>
        <div className="rounded-xl bg-amber-50 py-3">
          <div className="text-2xl font-extrabold text-amber-600">🔥 {stats.current_streak}</div>
          <div className="text-xs text-slate-500">{t('gam.streak')}</div>
        </div>
        <div className="rounded-xl bg-slate-50 py-3">
          <div className="text-2xl font-extrabold text-slate-700">#{stats.rank}</div>
          <div className="text-xs text-slate-500">{t('gam.rank')}</div>
        </div>
      </div>
      {stats.badges.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-2">
          {stats.badges.map((b) => (
            <span key={b} className="badge bg-emerald-50 text-emerald-700">🏅 {badgeLabel(b)}</span>
          ))}
        </div>
      )}
    </div>
  );
}
