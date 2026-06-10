'use client';

import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';

/**
 * Sticky warning bar shown while an admin is browsing as another user, with
 * a one-click return to their own account.
 */
export function ImpersonationBanner() {
  const { impersonating, stopImpersonating } = useAuth();
  const router = useRouter();

  if (!impersonating) return null;

  async function back() {
    await stopImpersonating();
    router.push('/admin/users');
  }

  return (
    <div className="sticky top-0 z-30 flex flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-amber-950">
      <span>
        أنت الآن تتصفّح المنصة باسم <strong>{impersonating}</strong>
      </span>
      <button
        onClick={() => void back()}
        className="rounded-lg bg-amber-950 px-3 py-1 text-xs font-bold text-amber-50 transition hover:bg-amber-900"
      >
        العودة إلى حساب الإدارة
      </button>
    </div>
  );
}
