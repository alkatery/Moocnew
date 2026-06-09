'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { t } from '@/i18n/dictionary';
import { ApiError } from '@/lib/api';

export default function RegisterPage() {
  const { register } = useAuth();
  const router = useRouter();
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [consent, setConsent] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await register({ ...form, consents: consent ? ['privacy_policy', 'data_processing'] : [] });
      router.push('/catalog');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="card" style={{ maxWidth: 420, margin: '0 auto' }}>
      <h1>{t('nav.register')}</h1>
      <form onSubmit={onSubmit}>
        <label className="label">{t('auth.name')}</label>
        <input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        <label className="label">{t('auth.email')}</label>
        <input className="input" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
        <label className="label">{t('auth.password')}</label>
        <input className="input" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
        <input className="input" type="password" placeholder="تأكيد كلمة المرور" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} required />
        <label className="label">
          <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} required /> {t('auth.consent')}
        </label>
        {error && <p className="error">{error}</p>}
        <button className="btn" disabled={busy} style={{ marginTop: 12 }}>{t('auth.submitRegister')}</button>
      </form>
    </section>
  );
}
