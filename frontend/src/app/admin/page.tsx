'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { t } from '@/i18n/dictionary';

type Overview = Record<string, number | boolean>;

const LABELS: Record<string, string> = {
  users_total: 'المستخدمون',
  courses_published: 'دورات منشورة',
  enrollments_total: 'إجمالي الالتحاقات',
  enrollments_active: 'التحاقات نشطة',
  enrollments_completed: 'مكتملة',
  certificates_issued: 'شهادات صادرة',
  online_now: 'متصلون الآن',
};

export default function AdminPage() {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [payments, setPayments] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ data: Overview }>('/analytics/overview')
      .then((r) => { setOverview(r.data); setPayments(Boolean(r.data.commerce_enabled)); })
      .catch(() => setError(t('common.error')));
  }, []);

  async function togglePayments() {
    try {
      const res = await api<{ payments_enabled: boolean }>('/admin/settings/payments', { method: 'PATCH', body: { enabled: !payments } });
      setPayments(res.payments_enabled);
    } catch { setError(t('common.error')); }
  }

  return (
    <section>
      <h1 className="mb-5">{t('admin.title')}</h1>
      {error && <p className="error mb-4">{error}</p>}

      <div className="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {overview && Object.entries(LABELS).map(([k, label]) => (
          <div key={k} className="card mb-0 text-center">
            <div className="text-3xl font-extrabold text-brand-700">{String(overview[k] ?? 0)}</div>
            <div className="mt-1 text-sm text-slate-500">{label}</div>
          </div>
        ))}
      </div>

      <div className="card flex items-center justify-between">
        <div>
          <strong className="text-slate-900">{t('admin.payments')}</strong>
          <p className="text-sm text-slate-500">تفعيل وحدة التجارة (الدفع والفواتير والسحوبات).</p>
        </div>
        <button
          role="switch" aria-checked={payments} onClick={() => void togglePayments()}
          className={`relative h-7 w-12 rounded-full transition ${payments ? 'bg-brand-600' : 'bg-slate-300'}`}>
          <span className={`absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-all ${payments ? 'start-0.5' : 'start-5.5'}`} />
        </button>
      </div>
    </section>
  );
}
