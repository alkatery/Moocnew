'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { t } from '@/i18n/dictionary';
import { api, ApiError } from '@/lib/api';
import { ErrorMsg } from '@/components/StatusMessage';

export default function LoginPage() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [unverified, setUnverified] = useState(false);
  const [resentNote, setResentNote] = useState('');

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError('');
    setUnverified(false);
    setResentNote('');
    try {
      await login(email, password);
      router.push('/learn');
    } catch (err) {
      if (err instanceof ApiError && err.code === 'email_unverified') {
        setUnverified(true);
        setError(t('auth.unverifiedHint'));
      } else {
        setError(err instanceof ApiError ? err.message : t('common.error'));
      }
    } finally {
      setBusy(false);
    }
  }

  async function resend() {
    setResentNote('');
    try {
      await api('/auth/email/resend', { method: 'POST', body: { email }, auth: false });
    } catch {
      // Generic endpoint — never surface details.
    }
    setResentNote(t('auth.resent'));
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
        <h1 className="mt-4 text-3xl font-extrabold">مرحباً بعودتك</h1>
        <p className="mt-2 text-sm text-slate-500">سجّل دخولك لتكمل من حيث توقفت.</p>
      </div>

      <form className="card" onSubmit={onSubmit}>
        <label className="label" htmlFor="login-email">{t('auth.email')}</label>
        <input id="login-email" className="input" type="email" dir="ltr"
          value={email} onChange={(e) => setEmail(e.target.value)} required />
        <label className="label" htmlFor="login-password">{t('auth.password')}</label>
        <input id="login-password" className="input" type="password"
          value={password} onChange={(e) => setPassword(e.target.value)} required />
        {/* G5: role="alert" عبر ErrorMsg */}
        <ErrorMsg msg={error} />
        {unverified && (
          <button className="btn btn-ghost mb-3 w-full" onClick={resend} type="button">
            {t('auth.resend')}
          </button>
        )}
        {/* G4: emerald-600 (3.77:1) → emerald-700 (5.48:1) — تمرير AA */}
        {resentNote && <p className="mb-3 text-sm text-emerald-700" role="status">{resentNote}</p>}
        <button className="btn w-full" disabled={busy}>
          {busy ? t('common.loading') : t('auth.submitLogin')}
        </button>
      </form>

      <p className="text-center text-sm text-slate-500">
        ليس لديك حساب؟ <Link className="font-semibold" href="/register">{t('nav.register')}</Link>
      </p>
    </section>
  );
}
