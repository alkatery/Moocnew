'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import type { Course, Paginated } from '@/lib/types';
import { t } from '@/i18n/dictionary';

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
      <h1>{t('studio.title')}</h1>
      <form className="card" onSubmit={create}>
        <strong>{t('studio.newCourse')}</strong>
        <input className="input" placeholder={t('common.title')} value={title} onChange={(e) => setTitle(e.target.value)} required />
        <select className="input" value={pricing} onChange={(e) => setPricing(e.target.value as 'free' | 'one_time')}>
          <option value="free">{t('course.free')}</option>
          <option value="one_time">{t('course.buy')}</option>
        </select>
        {pricing !== 'free' && (
          <input className="input" type="number" min="0" step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} />
        )}
        <button className="btn">{t('studio.create')}</button>
      </form>
      {loading ? <p className="label">{t('common.loading')}</p> : courses.map((c) => (
        <Link key={c.id} href={`/studio/${c.slug}`} className="card" style={{ display: 'block' }}>
          <strong>{c.title}</strong> <span className="badge">{c.status}</span>
        </Link>
      ))}
    </section>
  );
}
