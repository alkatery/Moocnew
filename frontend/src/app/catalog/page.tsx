'use client';

import Link from 'next/link';
import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { Category, Course, Paginated } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { CourseCard } from '@/components/CourseCard';

function CatalogInner() {
  const router = useRouter();
  const params = useSearchParams();

  const [q, setQ] = useState(params.get('q') ?? '');
  const [category, setCategory] = useState(params.get('category') ?? '');
  const [pricing, setPricing] = useState(params.get('pricing') ?? '');
  const [sort, setSort] = useState(params.get('sort') ?? 'newest');
  const [categories, setCategories] = useState<Category[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void api<Paginated<Category>>('/catalog/categories', { auth: false })
      .then((res) => setCategories(res.data)).catch(() => undefined);
  }, []);

  useEffect(() => {
    setLoading(true);
    const query = new URLSearchParams();
    if (q) query.set('q', q);
    if (category) query.set('category', category);
    if (pricing) query.set('pricing', pricing);
    if (sort && sort !== 'newest') query.set('sort', sort);
    const qs = query.toString();

    router.replace(qs ? `/catalog?${qs}` : '/catalog', { scroll: false });

    api<Paginated<Course>>(`/catalog/courses${qs ? `?${qs}` : ''}`, { auth: false })
      .then((res) => setCourses(res.data))
      .catch(() => setCourses([]))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q, category, pricing, sort]);

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-extrabold">{t('catalog.title')}</h1>
          <p className="mt-1 text-sm text-slate-500">
            ابحث وصفِّ حسب المجال أو السعر.{' '}
            <Link className="font-bold text-brand-700 hover:underline" href="/redeem">لديك كود التحاق؟</Link>
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <select className="input m-0 w-40" value={sort} onChange={(e) => setSort(e.target.value)} aria-label={t('catalog.sort')}>
            <option value="newest">{t('catalog.sortNewest')}</option>
            <option value="top_rated">{t('catalog.sortTopRated')}</option>
            <option value="popular">{t('catalog.sortPopular')}</option>
          </select>
          <input
            className="input m-0 w-64 max-w-full"
            placeholder={t('catalog.search')}
            value={q}
            onChange={(e) => setQ(e.target.value)}
            aria-label={t('catalog.search')}
          />
        </div>
      </div>

      <div className="mb-6 flex flex-wrap items-center gap-2">
        <button className={`chip ${category === '' ? 'chip-active' : ''}`} onClick={() => setCategory('')}>
          كل المجالات
        </button>
        {categories.map((c) => (
          <button
            key={c.id}
            className={`chip ${category === c.slug ? 'chip-active' : ''}`}
            onClick={() => setCategory(category === c.slug ? '' : c.slug)}
          >
            {c.name}
          </button>
        ))}
        <span className="mx-1 hidden h-5 w-px bg-slate-200 sm:block" aria-hidden />
        <button className={`chip ${pricing === 'free' ? 'chip-active' : ''}`}
          onClick={() => setPricing(pricing === 'free' ? '' : 'free')}>
          {t('course.free')}
        </button>
        <button className={`chip ${pricing === 'paid' ? 'chip-active' : ''}`}
          onClick={() => setPricing(pricing === 'paid' ? '' : 'paid')}>
          مدفوعة
        </button>
      </div>

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : courses.length === 0 ? (
        <div className="card text-center text-slate-500">{t('catalog.empty')}</div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {courses.map((c, i) => <CourseCard key={c.id} course={c} index={i} />)}
        </div>
      )}
    </section>
  );
}

export default function CatalogPage() {
  return (
    <Suspense fallback={<p className="label">{t('common.loading')}</p>}>
      <CatalogInner />
    </Suspense>
  );
}
