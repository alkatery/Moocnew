'use client';

import { useEffect, useRef, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { AssistantReply, AssistantTurn } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { ErrorMsg } from '@/components/StatusMessage';

interface Msg { role: 'user' | 'assistant'; content: string; sources?: string[] }

/**
 * Generic assistant chat panel. `endpoint` returns { conversation_id, reply };
 * `historyEndpoint` (optional) preloads prior turns. Used by both the learner
 * tutor and the admin analyst.
 */
export function ChatPanel({
  endpoint,
  historyEndpoint,
  placeholder,
  intro,
}: {
  endpoint: string;
  historyEndpoint?: string;
  placeholder: string;
  intro: string;
}) {
  const [messages, setMessages] = useState<Msg[]>([]);
  const [conversationId, setConversationId] = useState<number | null>(null);
  const [input, setInput] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!historyEndpoint) return;
    api<{ conversation_id: number | null; messages: AssistantTurn[] }>(historyEndpoint)
      .then((r) => {
        setConversationId(r.conversation_id);
        setMessages(r.messages.map((m) => ({ role: m.role, content: m.content, sources: m.meta?.sources })));
      })
      .catch(() => undefined);
  }, [historyEndpoint]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, busy]);

  async function send(e: React.FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || busy) return;
    setInput('');
    setError('');
    setMessages((m) => [...m, { role: 'user', content: text }]);
    setBusy(true);
    try {
      const res = await api<{ conversation_id: number; reply: AssistantReply }>(endpoint, {
        method: 'POST',
        body: { message: text, conversation_id: conversationId },
      });
      setConversationId(res.conversation_id);
      setMessages((m) => [...m, { role: 'assistant', content: res.reply.text, sources: res.reply.sources }]);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex-1 space-y-3 overflow-y-auto p-1">
        {messages.length === 0 && <p className="px-2 py-6 text-center text-sm text-slate-500">{intro}</p>}
        {messages.map((m, i) => (
          <div key={i} className={m.role === 'user' ? 'flex justify-start' : 'flex justify-end'}>
            <div className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm leading-7 ${
              m.role === 'user' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-800'
            }`}>
              <p className="whitespace-pre-line">{m.content}</p>
              {m.sources && m.sources.length > 0 && (
                <p className="mt-2 text-xs opacity-70">{t('assistant.sources')}: {m.sources.join('، ')}</p>
              )}
            </div>
          </div>
        ))}
        {busy && <p className="px-2 text-xs text-slate-500">{t('assistant.thinking')}</p>}
        <div ref={endRef} />
      </div>

      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={error} />

      <form className="flex gap-2 p-1" onSubmit={(e) => void send(e)}>
        <input className="input m-0 flex-1" placeholder={placeholder} value={input}
          onChange={(e) => setInput(e.target.value)} />
        <button className="btn shrink-0" disabled={busy}>{t('assistant.send')}</button>
      </form>
    </div>
  );
}
