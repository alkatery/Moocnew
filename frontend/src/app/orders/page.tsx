'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { Order, Paginated } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';

export default function OrdersPage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api<Paginated<Order>>('/commerce/orders')
      .then((r) => setOrders(r.data)).catch(() => setOrders([])).finally(() => setLoading(false));
  }, []);

  return (
    <section>
      <h1>{t('orders.title')}</h1>
      {loading ? <p className="label">{t('common.loading')}</p> : orders.map((o) => (
        <div key={o.id} className="card">
          طلب #{o.id} <span className="badge">{o.status}</span>
          <div className="label">{formatMinor(o.total_minor, o.currency)}</div>
        </div>
      ))}
    </section>
  );
}
