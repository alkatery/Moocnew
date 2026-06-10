'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { Order, Paginated } from '@/lib/types';
import { formatDate, formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { badgeTone, statusLabel } from '@/lib/labels';

export default function OrdersPage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api<Paginated<Order>>('/commerce/orders')
      .then((r) => setOrders(r.data)).catch(() => setOrders([])).finally(() => setLoading(false));
  }, []);

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader title={t('orders.title')} crumbs={[{ label: t('orders.title') }]} />

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : orders.length === 0 ? (
        <EmptyState
          text="لا توجد طلبات بعد."
          action={<Link className="btn" href="/catalog">{t('nav.catalog')}</Link>}
        />
      ) : (
        <div className="card p-0">
          <ul className="divide-y divide-slate-100">
            {orders.map((o) => (
              <li key={o.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                  <p className="font-bold text-slate-900">طلب #{o.id}</p>
                  {o.created_at && <time className="text-xs text-slate-400">{formatDate(o.created_at)}</time>}
                </div>
                <div className="flex items-center gap-3">
                  <span className={`badge ${badgeTone(o.status)}`}>{statusLabel(o.status)}</span>
                  <strong className="text-brand-700">{formatMinor(o.total_minor, o.currency)}</strong>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
