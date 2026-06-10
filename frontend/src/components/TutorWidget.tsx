'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { t } from '@/i18n/dictionary';
import { ChatPanel } from '@/components/ChatPanel';

/**
 * Floating course tutor «مُعين». Probes the history endpoint on mount; if the
 * assistant is disabled (404) the launcher stays hidden.
 */
export function TutorWidget({ courseSlug }: { courseSlug: string }) {
  const [enabled, setEnabled] = useState(false);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    api(`/assistant/courses/${courseSlug}/history`)
      .then(() => setEnabled(true))
      .catch((err) => setEnabled(!(err instanceof ApiError && err.status === 404)));
  }, [courseSlug]);

  if (!enabled) return null;

  return (
    <>
      {open && (
        <div className="fixed bottom-24 end-6 z-40 flex h-[28rem] w-[22rem] max-w-[calc(100vw-3rem)] flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <div className="flex items-center justify-between rounded-t-2xl bg-gradient-to-bl from-brand-700 to-brand-500 px-4 py-3 text-white">
            <strong className="text-sm">{t('assistant.tutor')}</strong>
            <button onClick={() => setOpen(false)} aria-label="إغلاق" className="text-white/80 hover:text-white">✕</button>
          </div>
          <div className="flex-1 overflow-hidden p-2">
            <ChatPanel
              endpoint={`/assistant/courses/${courseSlug}/chat`}
              historyEndpoint={`/assistant/courses/${courseSlug}/history`}
              placeholder={t('assistant.ask')}
              intro={t('assistant.intro')}
            />
          </div>
        </div>
      )}

      <button
        onClick={() => setOpen((o) => !o)}
        className="fixed bottom-6 end-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-xl transition hover:bg-brand-700"
        aria-label={t('assistant.tutor')}
      >
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="M4 5h16v11H9l-5 4z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" />
          <path d="M8 9h8M8 12h5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
        </svg>
      </button>
    </>
  );
}
