'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import type { Course, Order } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';

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
    <section className="card" style={{ maxWidth: 480, margin: '0 auto' }}>
      <h1>{t('checkout.title')}</h1>
      <p><strong>{course.title}</strong></p>
      <p className="label">{t('checkout.total')}: {formatMinor(course.price_minor, 'SAR')}</p>
      <label className="label">{t('checkout.coupon')}</label>
      <input className="input" value={coupon} onChange={(e) => setCoupon(e.target.value)} />
      {error && <p className="error">{error}</p>}
      {order ? (
        <div className="card">
          <p>طلب #{order.id} — {order.status}</p>
          <p className="label">{t('checkout.total')}: {formatMinor(order.total_minor, order.currency)}</p>
          <button className="btn" onClick={() => router.push('/orders')}>{t('orders.title')}</button>
        </div>
      ) : (
        <button className="btn" onClick={() => void pay()}>{t('checkout.pay')}</button>
      )}
    </section>
  );
}
