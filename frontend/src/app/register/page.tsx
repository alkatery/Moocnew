'use client';

import Link from 'next/link';
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
    <section className="mx-auto max-w-md py-6">
      <div className="mb-6 text-center">
        <span className="icon-tile mx-auto h-14 w-14 rounded-2xl">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M12 3 2 8l10 5 8-4v6h2V8L12 3Z" fill="#1f3a93" />
            <path d="M6 12.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-3.5l-6 3-6-3Z" fill="#5b6ef5" />
          </svg>
        </span>
        <h1 className="mt-4 text-3xl font-extrabold">أنشئ حسابك مجاناً</h1>
        <p className="mt-2 text-sm text-slate-500">دقيقة واحدة تفصلك عن أول دورة.</p>
      </div>

      <form className="card" onSubmit={onSubmit}>
        <label className="label" htmlFor="reg-name">{t('auth.name')}</label>
        <input id="reg-name" className="input"
          value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        <label className="label" htmlFor="reg-email">{t('auth.email')}</label>
        <input id="reg-email" className="input" type="email" dir="ltr"
          value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
        <div className="grid gap-x-3 sm:grid-cols-2">
          <div>
            <label className="label" htmlFor="reg-password">{t('auth.password')}</label>
            <input id="reg-password" className="input" type="password"
              value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
          </div>
          <div>
            <label className="label" htmlFor="reg-password2">تأكيد كلمة المرور</label>
            <input id="reg-password2" className="input" type="password"
              value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} required />
          </div>
        </div>
        <label className="mb-4 flex items-start gap-2 text-sm text-slate-600">
          <input className="mt-1" type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} required />
          {t('auth.consent')}
        </label>
        {error && <p className="error mb-3">{error}</p>}
        <button className="btn w-full" disabled={busy}>
          {busy ? t('common.loading') : t('auth.submitRegister')}
        </button>
      </form>

      <p className="text-center text-sm text-slate-500">
        لديك حساب بالفعل؟ <Link className="font-semibold" href="/login">{t('nav.login')}</Link>
      </p>
    </section>
  );
}
