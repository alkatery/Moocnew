'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { NotificationItem, Preference } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { channelLabel, notificationTypeLabel } from '@/lib/labels';

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

  // Group the flat preference list into a type × channel matrix.
  const types = Array.from(new Set(prefs.map((p) => p.type)));
  const channels = Array.from(new Set(prefs.map((p) => p.channel)));
  const byKey = new Map(prefs.map((p) => [`${p.type}.${p.channel}`, p]));

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader title={t('notifications.title')} crumbs={[{ label: t('notifications.title') }]} />

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : (
        <>
          {items.length === 0 ? (
            <EmptyState text="صندوق إشعاراتك فارغ." />
          ) : (
            <div className="card p-0">
              <ul className="divide-y divide-slate-100">
                {items.map((n) => (
                  <li key={n.id} className="flex items-center gap-3 px-5 py-4">
                    <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${n.read_at ? 'bg-slate-200' : 'bg-brand-500'}`} aria-hidden />
                    <span className={`flex-1 text-sm ${n.read_at ? 'text-slate-500' : 'font-bold text-slate-800'}`}>
                      {notificationTypeLabel(String(n.data['type'] ?? ''))}
                    </span>
                    {!n.read_at && (
                      <button className="btn btn-ghost" onClick={() => void markRead(n.id)}>
                        {t('notifications.markRead')}
                      </button>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          )}

          <h2 className="mb-4 mt-10 text-2xl font-extrabold">{t('notifications.preferences')}</h2>
          <div className="card overflow-x-auto p-0">
            <table className="w-full min-w-[480px] text-sm">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50 text-slate-500">
                  <th className="px-5 py-3 text-start font-bold">نوع الإشعار</th>
                  {channels.map((c) => (
                    <th key={c} className="px-3 py-3 text-center font-bold">{channelLabel(c)}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50">
                {types.map((type) => (
                  <tr key={type}>
                    <td className="px-5 py-3 font-medium text-slate-700">{notificationTypeLabel(type)}</td>
                    {channels.map((channel) => {
                      const p = byKey.get(`${type}.${channel}`);
                      return (
                        <td key={channel} className="px-3 py-3 text-center">
                          {p && (
                            <input
                              type="checkbox"
                              className="h-4 w-4 accent-brand-600"
                              checked={p.enabled}
                              onChange={() => void toggle(p)}
                              aria-label={`${notificationTypeLabel(type)} عبر ${channelLabel(channel)}`}
                            />
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </section>
  );
}
