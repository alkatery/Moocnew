import Link from 'next/link';

export interface Crumb {
  href?: string;
  label: string;
}

/**
 * Edraak-style page intro for internal pages: breadcrumb, big bold title,
 * optional subtitle/badge and an actions slot, over a hairline divider.
 */
export function PageHeader({
  title,
  subtitle,
  crumbs,
  badge,
  actions,
}: {
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  crumbs?: Crumb[];
  badge?: React.ReactNode;
  actions?: React.ReactNode;
}) {
  return (
    <header className="mb-6 border-b border-slate-200 pb-5">
      {crumbs && crumbs.length > 0 && (
        <nav className="mb-2 flex flex-wrap items-center gap-1.5 text-xs text-slate-400" aria-label="مسار التنقّل">
          <Link className="text-slate-400 transition hover:text-brand-600" href="/">الرئيسية</Link>
          {crumbs.map((c) => (
            <span key={c.label} className="flex items-center gap-1.5">
              <span aria-hidden>‹</span>
              {c.href ? (
                <Link className="text-slate-400 transition hover:text-brand-600" href={c.href}>{c.label}</Link>
              ) : (
                <span className="font-medium text-slate-500">{c.label}</span>
              )}
            </span>
          ))}
        </nav>
      )}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="flex flex-wrap items-center gap-3 text-3xl font-extrabold">
          {title}
          {badge}
        </h1>
        {actions && <div className="page-actions">{actions}</div>}
      </div>
      {subtitle && <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{subtitle}</p>}
    </header>
  );
}
