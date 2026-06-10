import Link from 'next/link';
import { t } from '@/i18n/dictionary';

const learnLinks = [
  { href: '/catalog', label: 'كل الدورات' },
  { href: '/catalog?pricing=free', label: 'المكتبة المجانية' },
  { href: '/news', label: 'أخبار المنصة' },
  { href: '/calendar', label: 'الجلسات المباشرة' },
];

const platformLinks = [
  { href: '/about', label: 'عن المنصة' },
  { href: '/contact', label: 'تواصل معنا' },
  { href: '/register', label: 'انضم كمتعلّم' },
  { href: '/register', label: 'درّس معنا' },
];

export function Footer() {
  return (
    <footer className="mt-16 bg-slate-900 text-slate-300">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <div className="flex items-center gap-2 text-lg font-extrabold text-white">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden>
              <path d="M12 3 2 8l10 5 8-4v6h2V8L12 3Z" fill="#8b9cf9" />
              <path d="M6 12.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-3.5l-6 3-6-3Z" fill="#5b6ef5" />
            </svg>
            {t('app.name')}
          </div>
          <p className="mt-3 text-sm leading-7 text-slate-400">
            منصة تعليم جماهيري مفتوح بالعربية: دورات فيديو تفاعلية، اختبارات،
            شهادات موثّقة، جلسات مباشرة ومجتمع نقاش — للجميع وفي أي وقت.
          </p>
        </div>

        <nav aria-label="التعلّم">
          <h3 className="mb-3 text-sm font-bold uppercase tracking-wider text-white">التعلّم</h3>
          <ul className="space-y-2 text-sm">
            {learnLinks.map((l) => (
              <li key={l.label}>
                <Link className="text-slate-400 transition hover:text-white" href={l.href}>{l.label}</Link>
              </li>
            ))}
          </ul>
        </nav>

        <nav aria-label="المنصة">
          <h3 className="mb-3 text-sm font-bold uppercase tracking-wider text-white">المنصة</h3>
          <ul className="space-y-2 text-sm">
            {platformLinks.map((l) => (
              <li key={l.label}>
                <Link className="text-slate-400 transition hover:text-white" href={l.href}>{l.label}</Link>
              </li>
            ))}
          </ul>
        </nav>

        <div>
          <h3 className="mb-3 text-sm font-bold uppercase tracking-wider text-white">الدعم</h3>
          <ul className="space-y-3 text-sm text-slate-400">
            <li className="flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                <path d="M4 6h16v12H4z" stroke="currentColor" strokeWidth="1.6" />
                <path d="m4 7 8 6 8-6" stroke="currentColor" strokeWidth="1.6" />
              </svg>
              <a className="text-slate-400 hover:text-white" href="mailto:support@mooc.example">support@mooc.example</a>
            </li>
            <li className="flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.6" />
                <path d="M12 7v5l3 3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
              </svg>
              نرد خلال يوم عمل واحد
            </li>
            <li>
              <Link className="btn btn-ghost border-slate-700 text-slate-200 ring-slate-700 hover:bg-slate-800" href="/contact">
                {t('nav.contact')}
              </Link>
            </li>
          </ul>
        </div>
      </div>

      <div className="border-t border-slate-800">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-slate-500">
          <span>© {new Date().getFullYear()} {t('app.name')} — جميع الحقوق محفوظة.</span>
          <span>صُنعت بشغف للتعليم المفتوح بالعربية.</span>
        </div>
      </div>
    </footer>
  );
}
