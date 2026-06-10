'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { CalendarEvent } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { statusLabel } from '@/lib/labels';

export default function CalendarPage() {
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api<{ data: CalendarEvent[] }>('/scheduling/calendar')
      .then((r) => setEvents(r.data)).catch(() => setEvents([])).finally(() => setLoading(false));
  }, []);

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader
        title={t('calendar.title')}
        subtitle="جلساتك المباشرة القادمة بالتاريخين الميلادي والهجري."
        crumbs={[{ label: t('calendar.title') }]}
      />

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : events.length === 0 ? (
        <EmptyState text="لا توجد جلسات قادمة في تقويمك." />
      ) : (
        <div className="space-y-3">
          {events.map((e, i) => {
            const date = new Date(e.at_local);
            return (
              <div key={i} className="card mb-0 flex items-center gap-4">
                <div className="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-2xl bg-brand-50 text-brand-700">
                  <span className="text-2xl font-extrabold leading-none">
                    {new Intl.DateTimeFormat('ar', { day: 'numeric' }).format(date)}
                  </span>
                  <span className="mt-0.5 text-xs font-semibold">
                    {new Intl.DateTimeFormat('ar', { month: 'short' }).format(date)}
                  </span>
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <strong className="text-slate-900">{e.title}</strong>
                    <span className="badge">{statusLabel(e.type)}</span>
                  </div>
                  <p className="mt-1 text-sm text-slate-500">
                    {new Intl.DateTimeFormat('ar', { dateStyle: 'full', timeStyle: 'short' }).format(date)}
                  </p>
                  <p className="text-xs text-slate-400">الموافق هجرياً: {e.hijri}</p>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </section>
  );
}
