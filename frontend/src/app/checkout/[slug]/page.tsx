'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import type { Course, Order } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { badgeTone, statusLabel } from '@/lib/labels';
import { ErrorMsg } from '@/components/StatusMessage';

export default function CheckoutPage() {
  const { slug } = useParams<{ slug: string }>();
  const router = useRouter();
  const [course, setCourse] = useState<Course | null>(null);
  const [coupon, setCoupon] = useState('');
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((r) => setCourse(r.data)).catch(() => setCourse(null));
  }, [slug]);

  async function pay() {
    setError('');
    try {
      const res = await api<{ data: Order }>('/commerce/checkout', {
        method: 'POST', body: { course_slug: slug, coupon: coupon || null },
      });
      setOrder(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  if (!course) return <p className="label">{t('common.loading')}</p>;

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader
        title={t('checkout.title')}
        crumbs={[{ href: '/catalog', label: t('nav.catalog') }, { href: `/catalog/${course.slug}`, label: course.title }, { label: t('checkout.title') }]}
      />

      <div className="grid gap-6 sm:grid-cols-2">
        {/* Order summary */}
        <div className="card mb-0 self-start">
          <strong className="text-slate-900">ملخص الطلب</strong>
          <div className="mt-4 flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
              <p className="font-bold text-slate-800">{course.title}</p>
              {course.instructor?.name && <p className="mt-0.5 text-xs text-slate-500">{course.instructor.name}</p>}
            </div>
            <span className="badge shrink-0">{formatMinor(course.price_minor, 'SAR')}</span>
          </div>
          <div className="mt-4 flex items-center justify-between">
            <span className="text-sm text-slate-500">{t('checkout.total')}</span>
            <strong className="text-xl text-brand-700">{formatMinor(course.price_minor, 'SAR')}</strong>
          </div>
        </div>

        {/* Payment */}
        <div>
          {order ? (
            <div className="card mb-0 text-center">
              <span className={`badge ${badgeTone(order.status)}`}>{statusLabel(order.status)}</span>
              <p className="mt-3 font-bold text-slate-900">طلب #{order.id}</p>
              <p className="mt-1 text-sm text-slate-500">
                {t('checkout.total')}: {formatMinor(order.total_minor, order.currency)}
              </p>
              <button className="btn mt-4 w-full" onClick={() => router.push('/orders')}>{t('orders.title')}</button>
            </div>
          ) : (
            <div className="card mb-0">
              <strong className="text-slate-900">الدفع</strong>
              <label className="label mt-3 block" htmlFor="co-coupon">{t('checkout.coupon')}</label>
              <input id="co-coupon" className="input" dir="ltr"
                value={coupon} onChange={(e) => setCoupon(e.target.value)} />
              {/* G5: role="alert" عبر ErrorMsg */}
              <ErrorMsg msg={error} />
              <button className="btn w-full" onClick={() => void pay()}>{t('checkout.pay')}</button>
              <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden>
                  <path d="M6 10V8a6 6 0 1 1 12 0v2m-13 0h14v11H5z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" />
                </svg>
                دفع آمن ومشفّر عبر مزوّد معتمد
              </p>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
