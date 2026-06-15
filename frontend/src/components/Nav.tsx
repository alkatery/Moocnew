'use client';

import Link from 'next/link';
import { useAuth } from '@/lib/auth';
import { useSiteContent } from '@/lib/siteContent';
import { t } from '@/i18n/dictionary';

function Logo() {
  const { c } = useSiteContent();
  const logo = c('brand.logo');
  const name = c('brand.name', t('app.name'));

  return (
    <span className="brand">
      {logo ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={logo} alt={name} className="h-7 w-auto max-w-[160px] object-contain" />
      ) : (
        <>
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M12 3 2 8l10 5 8-4v6h2V8L12 3Z" fill="#1f3a93" />
            <path d="M6 12.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-3.5l-6 3-6-3Z" fill="#5b6ef5" />
          </svg>
          {name}
        </>
      )}
    </span>
  );
}

export function Nav() {
  const { user, logout } = useAuth();
  const roles = user?.roles ?? [];
  const isInstructor = roles.includes('instructor') || roles.includes('super_admin');
  const isStaff = roles.includes('super_admin') || roles.includes('supervisor');

  return (
    <nav className="nav" aria-label="التنقّل الرئيسي">
      <div className="inner">
        <Link href="/"><Logo /></Link>
        <Link href="/catalog">{t('nav.catalog')}</Link>
        <Link href="/paths">{t('nav.paths')}</Link>
        <Link href="/news">{t('nav.news')}</Link>
        {!user && <Link className="hidden sm:inline" href="/about">{t('nav.about')}</Link>}
        {!user && <Link className="hidden sm:inline" href="/contact">{t('nav.contact')}</Link>}
        {user && <Link href="/learn">{t('nav.myLearning')}</Link>}
        {user && <Link className="hidden sm:inline" href="/leaderboard">{t('nav.leaderboard')}</Link>}
        {user && <Link href="/calendar">{t('nav.calendar')}</Link>}
        {isInstructor && <Link href="/studio">{t('nav.studio')}</Link>}
        {isStaff && <Link href="/admin">{t('nav.admin')}</Link>}
        {user && <Link href="/notifications">{t('nav.notifications')}</Link>}
        {user && <Link className="hidden sm:inline" href="/account">{t('nav.account')}</Link>}
        {user ? (
          <button className="btn" onClick={() => void logout()}>{t('nav.logout')}</button>
        ) : (
          <>
            <Link href="/login">{t('nav.login')}</Link>
            <Link className="btn" href="/register">{t('nav.register')}</Link>
          </>
        )}
      </div>
    </nav>
  );
}
