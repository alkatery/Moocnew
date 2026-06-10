'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { api } from './api';

type ContentMap = Record<string, string>;

interface SiteContentState {
  content: ContentMap;
  ready: boolean;
  /** Editable value for `key`, or the supplied fallback when unset. */
  c: (key: string, fallback?: string) => string;
}

const SiteContentContext = createContext<SiteContentState | null>(null);

export function SiteContentProvider({ children }: { children: React.ReactNode }) {
  const [content, setContent] = useState<ContentMap>({});
  const [ready, setReady] = useState(false);

  useEffect(() => {
    api<{ data: ContentMap }>('/content/site', { auth: false })
      .then((res) => setContent(res.data ?? {}))
      .catch(() => setContent({}))
      .finally(() => setReady(true));
  }, []);

  const c = (key: string, fallback = '') => content[key] ?? fallback;

  return (
    <SiteContentContext.Provider value={{ content, ready, c }}>
      {children}
    </SiteContentContext.Provider>
  );
}

export function useSiteContent(): SiteContentState {
  const ctx = useContext(SiteContentContext);
  if (!ctx) throw new Error('useSiteContent must be used within SiteContentProvider');
  return ctx;
}
