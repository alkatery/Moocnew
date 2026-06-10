'use client';

import { useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { t } from '@/i18n/dictionary';

const EMPTY = { name: '', email: '', phone: '', subject: '', message: '' };

export function ContactForm() {
  const [form, setForm] = useState(EMPTY);
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');

  function set<K extends keyof typeof EMPTY>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api('/contact', {
        method: 'POST',
        auth: false,
        body: { ...form, phone: form.phone || undefined },
      });
      setSent(true);
      setForm(EMPTY);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  if (sent) {
    return (
      <div className="card text-center">
        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="m5 13 4 4 10-10" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </div>
        <strong className="text-slate-900">{t('contact.success')}</strong>
        <p className="mt-2 text-sm text-slate-500">
          <button className="btn btn-ghost mt-2" onClick={() => setSent(false)}>إرسال رسالة أخرى</button>
        </p>
      </div>
    );
  }

  return (
    <form className="card" onSubmit={(e) => void submit(e)}>
      {error && <p className="error mb-3">{error}</p>}
      <div className="grid gap-x-3 sm:grid-cols-2">
        <div>
          <label className="label" htmlFor="cf-name">{t('contact.name')}</label>
          <input id="cf-name" className="input" required maxLength={120}
            value={form.name} onChange={(e) => set('name', e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="cf-email">{t('contact.email')}</label>
          <input id="cf-email" className="input" type="email" required maxLength={190}
            value={form.email} onChange={(e) => set('email', e.target.value)} />
        </div>
      </div>
      <div className="grid gap-x-3 sm:grid-cols-2">
        <div>
          <label className="label" htmlFor="cf-phone">{t('contact.phone')}</label>
          <input id="cf-phone" className="input" maxLength={32} dir="ltr"
            value={form.phone} onChange={(e) => set('phone', e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="cf-subject">{t('contact.subject')}</label>
          <input id="cf-subject" className="input" required maxLength={180}
            value={form.subject} onChange={(e) => set('subject', e.target.value)} />
        </div>
      </div>
      <label className="label" htmlFor="cf-message">{t('contact.message')}</label>
      <textarea id="cf-message" className="input min-h-32" required maxLength={5000}
        value={form.message} onChange={(e) => set('message', e.target.value)} />
      <button className="btn w-full" type="submit" disabled={busy}>
        {busy ? t('common.loading') : t('contact.send')}
      </button>
    </form>
  );
}
