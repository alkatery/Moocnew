'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { InstructorProfile } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { Stars } from '@/components/Stars';

export default function InstructorProfilePage() {
  const { id } = useParams<{ id: string }>();
  const [profile, setProfile] = useState<InstructorProfile | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    api<{ data: InstructorProfile }>(`/profiles/instructors/${id}`, { auth: false })
      .then((r) => setProfile(r.data)).catch(() => setMissing(true));
  }, [id]);

  if (missing) return <p className="label">{t('common.error')}</p>;
  if (!profile) return <p className="label">{t('common.loading')}</p>;

  return (
    <section>
      <PageHeader title={profile.name} crumbs={[{ label: t('profile.instructor') }]} />

      <div className="grid gap-6 md:grid-cols-3">
        <aside className="md:col-span-1">
          <div className="card">
            <div className="mx-auto mb-3 flex h-20 w-20 items-center justify-center rounded-full bg-brand-100 text-3xl font-extrabold text-brand-700">
              {profile.name.slice(0, 1)}
            </div>
            {profile.bio && <p className="text-sm leading-7 text-slate-600">{profile.bio}</p>}
            <div className="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
              <div><div className="font-extrabold text-brand-700">{profile.stats.courses}</div><div className="text-xs text-slate-400">{t('profile.courses')}</div></div>
              <div><div className="font-extrabold text-brand-700">{profile.stats.learners}</div><div className="text-xs text-slate-400">متعلّم</div></div>
              <div><div className="font-extrabold text-brand-700">{profile.stats.rating ?? '—'}</div><div className="text-xs text-slate-400">التقييم</div></div>
            </div>
          </div>
        </aside>

        <div className="md:col-span-2">
          <h2 className="mb-4 text-xl font-extrabold">{t('profile.courses')}</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            {profile.courses.map((c) => (
              <Link key={c.slug} href={`/catalog/${c.slug}`} className="card mb-0 transition hover:-translate-y-0.5 hover:shadow-lg">
                {c.cover_image && (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={c.cover_image} alt={c.title} className="mb-3 h-28 w-full rounded-xl object-cover" />
                )}
                <strong className="block text-slate-900">{c.title}</strong>
                {c.rating != null && <div className="mt-1"><Stars value={c.rating} size={13} /></div>}
              </Link>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
