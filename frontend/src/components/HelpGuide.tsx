import type { ReactNode } from 'react';

export interface GuideStep {
  title: string;
  body: ReactNode;
}

/**
 * Embedded, collapsible "how it works" guide rendered inline on a page.
 * Uses a native <details> element so it needs no client state and works
 * during SSR. RTL-aware, styled with the platform's card/brand tokens.
 */
export function HelpGuide({
  title = 'كيف يعمل هذا؟',
  intro,
  steps,
  defaultOpen = false,
}: {
  title?: string;
  intro?: ReactNode;
  steps: GuideStep[];
  defaultOpen?: boolean;
}) {
  return (
    <details className="group mb-6 rounded-2xl border border-brand-100 bg-brand-50/40 p-0" open={defaultOpen}>
      <summary className="flex cursor-pointer list-none items-center gap-3 px-5 py-4 font-bold text-brand-800">
        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.7" />
            <path d="M12 11v5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
            <circle cx="12" cy="7.6" r="1.1" fill="currentColor" />
          </svg>
        </span>
        <span className="flex-1">{title}</span>
        <svg className="text-brand-400 transition-transform group-open:rotate-180" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="m6 9 6 6 6-6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </summary>

      <div className="border-t border-brand-100 px-5 pb-5 pt-4">
        {intro && <p className="mb-4 text-sm leading-relaxed text-slate-600">{intro}</p>}
        <ol className="space-y-3">
          {steps.map((step, i) => (
            <li key={i} className="flex gap-3">
              <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                {i + 1}
              </span>
              <div className="pt-0.5">
                <strong className="block text-sm text-slate-900">{step.title}</strong>
                <span className="text-sm leading-relaxed text-slate-600">{step.body}</span>
              </div>
            </li>
          ))}
        </ol>
      </div>
    </details>
  );
}
