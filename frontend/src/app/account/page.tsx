'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { api, ApiError } from '@/lib/api';
import { t } from '@/i18n/dictionary';
import type { AuthUser } from '@/lib/types';

export default function AccountPage() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) router.replace('/login');
  }, [loading, user, router]);

  if (loading || !user) {
    return <section className="py-10 text-center text-slate-500">{t('common.loading')}</section>;
  }

  return (
    <section className="mx-auto max-w-xl py-6">
      <h1 className="mb-6 text-3xl font-extrabold">{t('account.title')}</h1>
      <ProfileSection user={user} />
      <PasswordSection />
      <PrivacySection onDeleted={async () => { await logout(); router.replace('/'); }} />
    </section>
  );
}

function Note({ ok, msg }: { ok: boolean; msg: string }) {
  if (!msg) return null;
  /* G4: emerald-600 (3.77:1) → emerald-700 (5.48:1) — تمرير AA للنص على أبيض */
  return (
    <p className={`mt-3 text-sm ${ok ? 'text-emerald-700' : 'text-rose-600'}`} role="status">{msg}</p>
  );
}

function ProfileSection({ user }: { user: AuthUser }) {
  const { refresh } = useAuth();
  const [name, setName] = useState(user.name ?? '');
  const [locale, setLocale] = useState(user.locale ?? 'ar');
  const [timezone, setTimezone] = useState(user.timezone ?? 'Asia/Riyadh');
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState('');
  const [ok, setOk] = useState(true);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setNote('');
    try {
      await api('/account', { method: 'PATCH', body: { name, locale, timezone } });
      await refresh(); // reflect the change in the nav / shared auth state
      setOk(true);
      setNote(t('account.saved'));
    } catch (err) {
      setOk(false);
      setNote(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="card" onSubmit={save}>
      <h2 className="mb-3 text-lg font-bold">{t('account.profile')}</h2>
      <label className="label" htmlFor="acc-name">{t('account.name')}</label>
      <input id="acc-name" className="input" value={name} onChange={(e) => setName(e.target.value)} />
      <label className="label" htmlFor="acc-locale">{t('account.language')}</label>
      <select id="acc-locale" className="input" value={locale} onChange={(e) => setLocale(e.target.value)}>
        <option value="ar">العربية</option>
        <option value="en">English</option>
      </select>
      <label className="label" htmlFor="acc-tz">{t('account.timezone')}</label>
      <input id="acc-tz" className="input" dir="ltr" value={timezone} onChange={(e) => setTimezone(e.target.value)} />
      <button className="btn mt-2" disabled={busy}>{busy ? t('common.loading') : t('common.save')}</button>
      <Note ok={ok} msg={note} />
    </form>
  );
}

function PasswordSection() {
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState('');
  const [ok, setOk] = useState(true);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setNote('');
    try {
      await api('/account/password', {
        method: 'PUT',
        body: { current_password: current, password: next, password_confirmation: confirm },
      });
      setOk(true);
      setNote(t('account.passwordChanged'));
      setCurrent(''); setNext(''); setConfirm('');
    } catch (err) {
      setOk(false);
      setNote(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="card" onSubmit={save}>
      <h2 className="mb-3 text-lg font-bold">{t('account.password')}</h2>
      <label className="label" htmlFor="acc-cur">{t('account.currentPassword')}</label>
      <input id="acc-cur" className="input" type="password" value={current} onChange={(e) => setCurrent(e.target.value)} required />
      <label className="label" htmlFor="acc-new">{t('account.newPassword')}</label>
      <input id="acc-new" className="input" type="password" value={next} onChange={(e) => setNext(e.target.value)} required />
      <label className="label" htmlFor="acc-conf">{t('account.confirmPassword')}</label>
      <input id="acc-conf" className="input" type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
      <button className="btn mt-2" disabled={busy}>{busy ? t('common.loading') : t('account.changePassword')}</button>
      <Note ok={ok} msg={note} />
    </form>
  );
}

function PrivacySection({ onDeleted }: { onDeleted: () => Promise<void> }) {
  const [password, setPassword] = useState('');
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState('');

  async function exportData() {
    setNote('');
    try {
      const res = await api<{ data: unknown }>('/account/export');
      const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'my-data.json';
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      setNote(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  async function remove(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setNote('');
    try {
      await api('/account', { method: 'DELETE', body: { current_password: password } });
      await onDeleted();
    } catch (err) {
      setNote(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="card">
      <h2 className="mb-3 text-lg font-bold">{t('account.privacy')}</h2>
      <p className="mb-2 text-sm text-slate-500">{t('account.exportHint')}</p>
      <button className="btn btn-ghost mb-5" type="button" onClick={exportData}>{t('account.export')}</button>

      <hr className="mb-4 border-slate-100" />
      <p className="mb-2 text-sm text-slate-500">{t('account.deleteHint')}</p>
      {!confirming ? (
        <button className="btn btn-ghost text-rose-600 ring-rose-200" type="button" onClick={() => setConfirming(true)}>
          {t('account.delete')}
        </button>
      ) : (
        <form onSubmit={remove}>
          <input className="input" type="password" placeholder={t('account.currentPassword')}
            value={password} onChange={(e) => setPassword(e.target.value)} required aria-label={t('account.currentPassword')} />
          <button className="btn mt-2 bg-rose-600 hover:bg-rose-700" disabled={busy}>
            {busy ? t('common.loading') : t('account.deleteConfirm')}
          </button>
        </form>
      )}
      <Note ok={false} msg={note} />
    </div>
  );
}
