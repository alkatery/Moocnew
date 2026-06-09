'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { t } from '@/i18n/dictionary';

type Overview = Record<string, number | boolean>;

export default function AdminPage() {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [payments, setPayments] = useState(false);
  const [error, setError] = useState('');

  function load() {
    api<{ data: Overview }>('/analytics/overview')
      .then((r) => { setOverview(r.data); setPayments(Boolean(r.data.commerce_enabled)); })
      .catch(() => setError(t('common.error')));
  }
  useEffect(load, []);

  async function togglePayments() {
    try {
      const res = await api<{ payments_enabled: boolean }>('/admin/settings/payments', {
        method: 'PATCH', body: { enabled: !payments },
      });
      setPayments(res.payments_enabled);
    } catch {
      setError(t('common.error'));
    }
  }

  return (
    <section>
      <h1>{t('admin.title')}</h1>
      {error && <p className="error">{error}</p>}
      <div className="card">
        <strong>{t('admin.payments')}</strong>
        <p><label><input type="checkbox" checked={payments} onChange={() => void togglePayments()} /> {payments ? 'on' : 'off'}</label></p>
      </div>
      <div className="card">
        <strong>{t('admin.analytics')}</strong>
        {!overview ? <p className="label">{t('common.loading')}</p> : (
          <ul>
            {Object.entries(overview).map(([k, v]) => <li key={k}>{k}: {String(v)}</li>)}
          </ul>
        )}
      </div>
    </section>
  );
}
