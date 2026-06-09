'use client';

import Link from 'next/link';
import { useAuth } from '@/lib/auth';
import { t } from '@/i18n/dictionary';

export function Nav() {
  const { user, logout } = useAuth();
  const roles = user?.roles ?? [];
  const isInstructor = roles.includes('instructor') || roles.includes('super_admin');
  const isStaff = roles.includes('super_admin') || roles.includes('supervisor');

  return (
    <nav className="nav">
      <div className="inner">
        <Link href="/" className="brand">{t('app.name')}</Link>
        <Link href="/catalog">{t('nav.catalog')}</Link>
        {user && <Link href="/learn">{t('nav.myLearning')}</Link>}
        {user && <Link href="/calendar">{t('nav.calendar')}</Link>}
        {isInstructor && <Link href="/studio">{t('nav.studio')}</Link>}
        {isStaff && <Link href="/admin">{t('nav.admin')}</Link>}
        {user && <Link href="/notifications">{t('nav.notifications')}</Link>}
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
