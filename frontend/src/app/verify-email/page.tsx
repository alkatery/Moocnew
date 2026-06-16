'use client';

import Link from 'next/link';
import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { t } from '@/i18n/dictionary';

type Status = 'verifying' | 'success' | 'failed';

function VerifyEmailInner() {
  const params = useSearchParams();
  const router = useRouter();
  const { adoptSession } = useAuth();
  const [status, setStatus] = useState<Status>('verifying');

  useEffect(() => {
    // The mail links to the SPA carrying the still-signed API URL (verify_url).
    // A pre-resolved `status` (server redirect fallback) is also honoured.
    const preset = params.get('status');
    if (preset === 'success' || preset === 'already') {
      setStatus('success');
      return;
    }

    const verifyUrl = params.get('verify_url');
    if (!verifyUrl) {
      setStatus('failed');
      return;
    }

    let cancelled = false;
    (async () => {
      try {
        const res = await fetch(verifyUrl, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('verify failed');
        const data = await res.json().catch(() => ({}));
        if (cancelled) return;
        if (typeof data?.token === 'string') {
          await adoptSession(data.token);
        }
        setStatus('success');
      } catch {
        if (!cancelled) setStatus('failed');
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [params, adoptSession]);

  return (
    <section className="mx-auto max-w-md py-10 text-center">
      {status === 'verifying' && (
        <p className="text-slate-500" role="status" aria-live="polite">{t('auth.verifying')}</p>
      )}

      {status === 'success' && (
        <>
          <h1 className="text-2xl font-extrabold text-emerald-600">{t('auth.verifySuccess')}</h1>
          <button className="btn mt-6" type="button" onClick={() => router.push('/learn')}>
            {t('auth.goLearn')}
          </button>
        </>
      )}

      {status === 'failed' && (
        <>
          <h1 className="text-2xl font-extrabold">{t('auth.verifyFailed')}</h1>
          <p className="mt-6 text-sm text-slate-500">
            <Link className="font-semibold" href="/login">{t('auth.goLogin')}</Link>
          </p>
        </>
      )}
    </section>
  );
}

export default function VerifyEmailPage() {
  return (
    <Suspense fallback={<section className="py-10 text-center text-slate-500">{t('common.loading')}</section>}>
      <VerifyEmailInner />
    </Suspense>
  );
}
