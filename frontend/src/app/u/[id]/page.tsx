'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { LearnerProfile } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { badgeLabel } from '@/components/AchievementsCard';

export default function LearnerProfilePage() {
  const { id } = useParams<{ id: string }>();
  const [profile, setProfile] = useState<LearnerProfile | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    api<{ data: LearnerProfile }>(`/profiles/learners/${id}`, { auth: false })
      .then((r) => setProfile(r.data)).catch(() => setMissing(true));
  }, [id]);

  if (missing) return <p className="label">{t('common.error')}</p>;
  if (!profile) return <p className="label">{t('common.loading')}</p>;

  return (
    <section className="mx-auto max-w-2xl">
      <PageHeader title={profile.name} subtitle={`${t('profile.joined')} ${formatDate(profile.joined_at)}`} crumbs={[{ label: t('profile.learner') }]} />

      <div className="mb-6 grid grid-cols-3 gap-3 text-center">
        <div className="card mb-0 py-4">
          <div className="text-2xl font-extrabold text-brand-700">{profile.points}</div>
          <div className="text-xs text-slate-500">{t('gam.points')}</div>
        </div>
        <div className="card mb-0 py-4">
          <div className="text-2xl font-extrabold text-amber-600">🔥 {profile.current_streak}</div>
          <div className="text-xs text-slate-500">{t('gam.streak')}</div>
        </div>
        <div className="card mb-0 py-4">
          <div className="text-2xl font-extrabold text-emerald-600">{profile.certificates.length}</div>
          <div className="text-xs text-slate-500">{t('profile.certificates')}</div>
        </div>
      </div>

      {profile.badges.length > 0 && (
        <div className="card">
          <strong className="text-slate-900">{t('gam.badges')}</strong>
          <div className="mt-3 flex flex-wrap gap-2">
            {profile.badges.map((b) => <span key={b} className="badge bg-emerald-50 text-emerald-700">🏅 {badgeLabel(b)}</span>)}
          </div>
        </div>
      )}

      {profile.certificates.length > 0 && (
        <div className="card p-0">
          <div className="border-b border-slate-100 p-4"><strong className="text-slate-900">{t('profile.certificates')}</strong></div>
          <ul className="divide-y divide-slate-100">
            {profile.certificates.map((c) => (
              <li key={c.verification_uuid} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                <span className="text-slate-700">{c.title}</span>
                <span className="flex items-center gap-2 text-xs text-slate-500">
                  {c.grade != null && <span className="badge bg-emerald-50 text-emerald-700">{c.grade}%</span>}
                  <time>{formatDate(c.issued_at)}</time>
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
