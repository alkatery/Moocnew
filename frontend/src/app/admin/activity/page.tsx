'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { ActivityLogRow } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';

export default function AdminActivityPage() {
  const [rows, setRows] = useState<ActivityLogRow[]>([]);
  const [events, setEvents] = useState<string[]>([]);
  const [event, setEvent] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    const qs = event ? `?event=${encodeURIComponent(event)}` : '';
    api<{ data: ActivityLogRow[]; events: string[] }>(`/admin/activity-logs${qs}`)
      .then((r) => { setRows(r.data); if (events.length === 0) setEvents(r.events); })
      .catch(() => setError(t('common.error')));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [event]);

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>{t('activity.title')}</h1>
        <Link className="btn btn-ghost" href="/admin">{t('admin.title')}</Link>
      </div>
      {error && <p className="error mb-4">{error}</p>}

      <div className="mb-4">
        <select className="input m-0 w-64 max-w-full" value={event} onChange={(e) => setEvent(e.target.value)}>
          <option value="">{t('activity.allEvents')}</option>
          {events.map((ev) => <option key={ev} value={ev}>{ev}</option>)}
        </select>
      </div>

      <div className="card p-0">
        <ul className="divide-y divide-slate-100">
          {rows.map((r) => (
            <li key={r.id} className="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
              <span className="badge font-mono" dir="ltr">{r.event}</span>
              <span className="flex-1 text-slate-600">
                {r.causer ?? 'النظام'}
                {r.subject_type && <span className="text-slate-400"> · {r.subject_type}#{r.subject_id}</span>}
              </span>
              {r.created_at && <time className="text-xs text-slate-400">{formatDate(r.created_at)}</time>}
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
