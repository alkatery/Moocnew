'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { NotificationItem, Preference, DigestFrequency, PreferencesResponse } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';
import { channelLabel, notificationTypeLabel } from '@/lib/labels';

// خيارات تكرار الملخّص — §9.أ من عقد D3
const DIGEST_OPTIONS: { value: DigestFrequency; labelKey: 'notifications.digestOff' | 'notifications.digestDaily' | 'notifications.digestWeekly' }[] = [
  { value: 'off',    labelKey: 'notifications.digestOff' },
  { value: 'daily',  labelKey: 'notifications.digestDaily' },
  { value: 'weekly', labelKey: 'notifications.digestWeekly' },
];

export default function NotificationsPage() {
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [prefs, setPrefs] = useState<Preference[]>([]);
  // تكرار الملخّص الحالي — يُقرأ من digest.frequency في استجابة GET /notifications/preferences
  const [digestFrequency, setDigestFrequency] = useState<DigestFrequency>('off');
  // حالة حفظ الملخّص
  const [digestSaving, setDigestSaving] = useState(false);
  const [digestSuccess, setDigestSuccess] = useState('');
  const [digestError, setDigestError]   = useState('');
  const [loading, setLoading] = useState(true);

  function loadInbox() {
    api<{ data: NotificationItem[] }>('/notifications')
      .then((r) => setItems(r.data)).catch(() => setItems([]));
  }

  function loadPrefs() {
    // نستهلك PreferencesResponse الكاملة (data + digest) — §5.أ من عقد D3
    api<PreferencesResponse>('/notifications/preferences')
      .then((r) => {
        setPrefs(r.data);
        // r.digest قد يكون غائباً إن لم يُنفَّذ الخلفي بعد — نوفّر قيمة احتياطية آمنة
        setDigestFrequency(r.digest?.frequency ?? 'off');
      })
      .catch(() => setPrefs([]))
      .finally(() => setLoading(false));
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

  /**
   * حفظ تكرار الملخّص — §5.ب من عقد D3
   * يرسل PUT /notifications/preferences بحقل digest_frequency فقط
   * (لا يلمس مصفوفة القنوات)
   */
  async function saveDigestFrequency(value: DigestFrequency) {
    setDigestSaving(true);
    setDigestSuccess('');
    setDigestError('');
    try {
      const res = await api<PreferencesResponse>('/notifications/preferences', {
        method: 'PUT',
        body: { digest_frequency: value },
      });
      // تحديث الحالة من استجابة الخادم (مصدر الحقيقة)
      setDigestFrequency(res.digest?.frequency ?? value);
      setDigestSuccess(t('notifications.digestSaved'));
    } catch {
      setDigestError(t('notifications.digestError'));
    } finally {
      setDigestSaving(false);
    }
  }

  // بناء مصفوفة type × channel
  const types    = Array.from(new Set(prefs.map((p) => p.type)));
  const channels = Array.from(new Set(prefs.map((p) => p.channel)));
  const byKey    = new Map(prefs.map((p) => [`${p.type}.${p.channel}`, p]));

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
                    <span
                      className={`h-2.5 w-2.5 shrink-0 rounded-full ${n.read_at ? 'bg-slate-200' : 'bg-brand-500'}`}
                      aria-hidden
                    />
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

          {/* ===== قسم تكرار الملخّص — D3 §9.أ ===== */}
          <section aria-labelledby="digest-section-heading" className="mt-10">
            <h2 id="digest-section-heading" className="mb-1 text-2xl font-extrabold">
              {t('notifications.digestTitle')}
            </h2>
            {/* النصّ التوضيحي — §9.أ */}
            <p className="mb-4 text-sm text-slate-500">{t('notifications.digestHint')}</p>

            {/* رسائل الحالة — §9.د */}
            <SuccessMsg msg={digestSuccess} />
            <ErrorMsg   msg={digestError}  />

            {/*
              مجموعة راديو داخل fieldset + legend — §9.د (a11y: fieldset/legend للراديو).
              تباين AA مضمون بألوان slate-700 / brand-600.
              تشغيل بلوحة المفاتيح: Tab ينتقل بين الخيارات، Space يختار.
            */}
            <fieldset
              disabled={digestSaving}
              className="card p-5"
            >
              <legend className="sr-only">{t('notifications.digestTitle')}</legend>
              <div className="flex flex-col gap-3 sm:flex-row sm:gap-6">
                {DIGEST_OPTIONS.map(({ value, labelKey }) => (
                  <label
                    key={value}
                    className={`flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors
                      ${digestFrequency === value
                        ? 'border-brand-600 bg-brand-50 text-brand-700'
                        : 'border-slate-200 text-slate-700 hover:border-brand-300 hover:bg-slate-50'}
                      ${digestSaving ? 'opacity-60 cursor-not-allowed' : ''}
                    `}
                  >
                    <input
                      type="radio"
                      name="digest_frequency"
                      value={value}
                      checked={digestFrequency === value}
                      onChange={() => void saveDigestFrequency(value)}
                      disabled={digestSaving}
                      className="accent-brand-600 h-4 w-4"
                    />
                    {t(labelKey)}
                  </label>
                ))}
              </div>
              {/* مؤشّر الحفظ الجاري للقارئات الشاشة */}
              {digestSaving && (
                <p className="sr-only" aria-live="polite">{t('common.loading')}</p>
              )}
            </fieldset>
          </section>
          {/* ===== نهاية قسم الملخّص ===== */}

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
