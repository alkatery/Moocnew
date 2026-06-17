'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import type { RedeemResult } from '@/lib/types';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';

export default function RedeemPage() {
  const router = useRouter();
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  async function redeem(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setError(''); setSuccess('');
    try {
      const res = await api<{ data: RedeemResult }>('/enrollment-codes/redeem', {
        method: 'POST',
        body: { code: code.trim() },
      });
      setSuccess(`تم التحاقك بدورة «${res.data.title}». جارٍ التحويل…`);
      router.push(`/learn/${res.data.slug}`);
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        setError('يرجى تسجيل الدخول أولاً لاستخدام الكود.');
      } else {
        setError(err instanceof ApiError ? err.message : 'تعذّر استخدام الكود.');
      }
      setBusy(false);
    }
  }

  return (
    <section className="mx-auto max-w-md">
      <h1 className="mb-2">استخدام كود التحاق</h1>
      <p className="mb-5 text-sm text-slate-500">
        أدخل الكود الذي حصلت عليه من مدرّسك أو مؤسستك للالتحاق بالدورة مباشرة.
      </p>

      <form className="card" onSubmit={(e) => void redeem(e)}>
        {/* G5: role="alert"/"status" عبر ErrorMsg/SuccessMsg */}
        <ErrorMsg msg={error} />
        <SuccessMsg msg={success} />

        <label className="label block" htmlFor="redeem-code">كود الالتحاق</label>
        <input
          id="redeem-code"
          className="input text-center font-mono text-lg font-bold tracking-widest uppercase"
          dir="ltr"
          maxLength={16}
          required
          value={code}
          onChange={(e) => setCode(e.target.value.toUpperCase())}
          placeholder="XXXXXXXX"
        />
        <button className="btn w-full" disabled={busy || code.trim() === ''}>
          {busy ? 'جارٍ التحقق…' : 'التحق بالدورة'}
        </button>
      </form>
    </section>
  );
}
