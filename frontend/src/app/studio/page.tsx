'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import type { Course, Paginated } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { badgeTone, statusLabel } from '@/lib/labels';

export default function StudioPage() {
  const [courses, setCourses] = useState<Course[]>([]);
  const [title, setTitle] = useState('');
  const [pricing, setPricing] = useState<'free' | 'one_time'>('free');
  const [price, setPrice] = useState('0');
  const [loading, setLoading] = useState(true);

  function load() {
    api<Paginated<Course>>('/catalog/mine')
      .then((r) => setCourses(r.data))
      .catch(() => setCourses([]))
      .finally(() => setLoading(false));
  }
  useEffect(load, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    await api('/catalog/courses', {
      method: 'POST',
      body: { title, pricing_type: pricing, price_minor: pricing === 'free' ? 0 : Math.round(parseFloat(price) * 100) },
    });
    setTitle('');
    setPrice('0');
    load();
  }

  return (
    <section>
      <PageHeader
        title={t('studio.title')}
        subtitle="أنشئ دوراتك، ابنِ المنهج، وأرسلها للمراجعة والنشر."
        crumbs={[{ label: t('nav.studio') }]}
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <form className="card mb-0 self-start lg:order-2" onSubmit={create}>
          <strong className="text-slate-900">{t('studio.newCourse')}</strong>
          <label className="label mt-3 block" htmlFor="st-title">{t('common.title')}</label>
          <input id="st-title" className="input" value={title} onChange={(e) => setTitle(e.target.value)} required />
          <label className="label" htmlFor="st-pricing">التسعير</label>
          <select id="st-pricing" className="input" value={pricing}
            onChange={(e) => setPricing(e.target.value as 'free' | 'one_time')}>
            <option value="free">{t('course.free')}</option>
            <option value="one_time">مدفوعة — دفعة واحدة</option>
          </select>
          {pricing !== 'free' && (
            <>
              <label className="label" htmlFor="st-price">السعر (ر.س)</label>
              <input id="st-price" className="input" type="number" min="0" step="0.01" dir="ltr"
                value={price} onChange={(e) => setPrice(e.target.value)} />
            </>
          )}
          <button className="btn w-full">{t('studio.create')}</button>
        </form>

        <div className="lg:col-span-2 lg:order-1">
          {loading ? (
            <p className="label">{t('common.loading')}</p>
          ) : courses.length === 0 ? (
            <EmptyState text="لا توجد دورات بعد — أنشئ دورتك الأولى من النموذج المجاور." />
          ) : (
            <div className="card p-0">
              <ul className="divide-y divide-slate-100">
                {courses.map((c) => (
                  <li key={c.id}>
                    <Link href={`/studio/${c.slug}`}
                      className="flex items-center gap-3 px-5 py-4 transition hover:bg-brand-50/50">
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
                          <path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 0-3 3V4Zm0 13h14" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                      </span>
                      <span className="min-w-0 flex-1">
                        <strong className="block truncate text-slate-900">{c.title}</strong>
                        <span className="text-xs text-slate-400">
                          {c.pricing_type === 'free' ? t('course.free') : formatMinor(c.price_minor)}
                        </span>
                      </span>
                      <span className={`badge ${badgeTone(c.status)}`}>{statusLabel(c.status)}</span>
                      <svg className="text-slate-300 rtl:rotate-180" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                        <path d="m9 6 6 6-6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                      </svg>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
