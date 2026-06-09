'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { CalendarEvent } from '@/lib/types';
import { t } from '@/i18n/dictionary';

export default function CalendarPage() {
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api<{ data: CalendarEvent[] }>('/scheduling/calendar')
      .then((r) => setEvents(r.data)).catch(() => setEvents([])).finally(() => setLoading(false));
  }, []);

  return (
    <section>
      <h1>{t('calendar.title')}</h1>
      {loading ? <p className="label">{t('common.loading')}</p> : events.length === 0 ? (
        <p className="label">{t('catalog.empty')}</p>
      ) : events.map((e, i) => (
        <div key={i} className="card">
          <strong>{e.title}</strong> <span className="badge">{e.type}</span>
          <div className="label">{new Date(e.at_local).toLocaleString('ar-SA')} — هـ {e.hijri}</div>
        </div>
      ))}
    </section>
  );
}
