'use client';

import Link from 'next/link';
import { useAuth } from '@/lib/auth';
import { t } from '@/i18n/dictionary';

export function Nav() {
  const { user, logout } = useAuth();
  return (
    <nav className="nav">
      <div className="inner">
        <Link href="/" className="brand">{t('app.name')}</Link>
        <Link href="/catalog">{t('nav.catalog')}</Link>
        {user && <Link href="/learn">{t('nav.myLearning')}</Link>}
        {user ? (
          <button className="btn" onClick={() => void logout()}>{t('nav.logout')}</button>
        ) : (
          <>
            <Link href="/login">{t('nav.login')}</Link>
            <Link href="/register">{t('nav.register')}</Link>
          </>
        )}
      </div>
    </nav>
  );
}
