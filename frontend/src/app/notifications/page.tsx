'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { NotificationItem, Preference } from '@/lib/types';
import { t } from '@/i18n/dictionary';

export default function NotificationsPage() {
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [prefs, setPrefs] = useState<Preference[]>([]);
  const [loading, setLoading] = useState(true);

  function loadInbox() {
    api<{ data: NotificationItem[] }>('/notifications')
      .then((r) => setItems(r.data)).catch(() => setItems([]));
  }
  function loadPrefs() {
    api<{ data: Preference[] }>('/notifications/preferences')
      .then((r) => setPrefs(r.data)).catch(() => setPrefs([])).finally(() => setLoading(false));
  }
  useEffect(() => { loadInbox(); loadPrefs(); }, []);

  async function markRead(id: string) {
    await api(`/notifications/${id}/read`, { method: 'POST' }).catch(() => {});
    loadInbox();
  }

  async function toggle(p: Preference) {
    await api('/notifications/preferences', {
      method: 'PUT',
      body: { preferences: [{ type: p.type, channel: p.channel, enabled: !p.enabled }] },
    }).catch(() => {});
    loadPrefs();
  }

  return (
    <section>
      <h1>{t('notifications.title')}</h1>
      {loading ? <p className="label">{t('common.loading')}</p> : (
        <>
          {items.map((n) => (
            <div key={n.id} className="card">
              <span>{String(n.data['type'] ?? '')}</span>
              {!n.read_at && (
                <button className="btn" style={{ marginInlineStart: 8 }} onClick={() => void markRead(n.id)}>
                  {t('notifications.markRead')}
                </button>
              )}
            </div>
          ))}
          <h2>{t('notifications.preferences')}</h2>
          <div className="card">
            {prefs.map((p) => (
              <label key={`${p.type}.${p.channel}`} style={{ display: 'block', margin: '4px 0' }}>
                <input type="checkbox" checked={p.enabled} onChange={() => void toggle(p)} /> {p.type} · {p.channel}
              </label>
            ))}
          </div>
        </>
      )}
    </section>
  );
}
