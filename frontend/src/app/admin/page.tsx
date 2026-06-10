'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { AssistantMode, ContactMessageItem, Paginated } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { ChatPanel } from '@/components/ChatPanel';

type Overview = Record<string, number | boolean>;

const LABELS: Record<string, string> = {
  users_total: 'المستخدمون',
  courses_published: 'دورات منشورة',
  enrollments_total: 'إجمالي الالتحاقات',
  enrollments_active: 'التحاقات نشطة',
  enrollments_completed: 'مكتملة',
  certificates_issued: 'شهادات صادرة',
  online_now: 'متصلون الآن',
};

export default function AdminPage() {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [payments, setPayments] = useState(false);
  const [assistantMode, setAssistantMode] = useState<AssistantMode>('off');
  const [messages, setMessages] = useState<ContactMessageItem[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ data: Overview }>('/analytics/overview')
      .then((r) => { setOverview(r.data); setPayments(Boolean(r.data.commerce_enabled)); })
      .catch(() => setError(t('common.error')));
    api<{ payments_enabled: boolean; assistant_mode: AssistantMode }>('/admin/settings')
      .then((r) => setAssistantMode(r.assistant_mode))
      .catch(() => undefined);
    api<Paginated<ContactMessageItem>>('/admin/contact-messages?status=new')
      .then((r) => setMessages(r.data))
      .catch(() => undefined);
  }, []);

  async function togglePayments() {
    try {
      const res = await api<{ payments_enabled: boolean }>('/admin/settings/payments', { method: 'PATCH', body: { enabled: !payments } });
      setPayments(res.payments_enabled);
    } catch { setError(t('common.error')); }
  }

  async function changeAssistantMode(mode: AssistantMode) {
    try {
      const res = await api<{ assistant_mode: AssistantMode }>('/admin/settings/assistant', { method: 'PATCH', body: { mode } });
      setAssistantMode(res.assistant_mode);
    } catch { setError(t('common.error')); }
  }

  async function markHandled(id: number) {
    try {
      await api(`/admin/contact-messages/${id}/handle`, { method: 'POST' });
      setMessages((prev) => prev.filter((m) => m.id !== id));
    } catch { setError(t('common.error')); }
  }

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>{t('admin.title')}</h1>
        <div className="page-actions">
          <Link className="btn btn-ghost" href="/admin/users">{t('admin.users')}</Link>
          <Link className="btn btn-ghost" href="/admin/activity">{t('admin.activity')}</Link>
          <Link className="btn btn-ghost" href="/admin/content">{t('admin.content')}</Link>
          <Link className="btn btn-ghost" href="/admin/paths">إدارة المسارات</Link>
          <Link className="btn btn-ghost" href="/admin/quality">جودة التعليم</Link>
          <Link className="btn btn-ghost" href="/admin/news">{t('admin.news')}</Link>
        </div>
      </div>
      {error && <p className="error mb-4">{error}</p>}

      <div className="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {overview && Object.entries(LABELS).map(([k, label]) => (
          <div key={k} className="card mb-0 text-center">
            <div className="text-3xl font-extrabold text-brand-700">{String(overview[k] ?? 0)}</div>
            <div className="mt-1 text-sm text-slate-500">{label}</div>
          </div>
        ))}
      </div>

      <div className="card flex items-center justify-between">
        <div>
          <strong className="text-slate-900">{t('admin.payments')}</strong>
          <p className="text-sm text-slate-500">تفعيل وحدة التجارة (الدفع والفواتير والسحوبات).</p>
        </div>
        <button
          role="switch" aria-checked={payments} onClick={() => void togglePayments()}
          className={`relative h-7 w-12 rounded-full transition ${payments ? 'bg-brand-600' : 'bg-slate-300'}`}>
          <span className={`absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-all ${payments ? 'start-0.5' : 'start-5.5'}`} />
        </button>
      </div>

      {/* AI assistant engine selector */}
      <div className="card">
        <strong className="text-slate-900">{t('admin.assistantMode')}</strong>
        <p className="mb-3 text-sm text-slate-500">اختر محرّك المساعد الذكي (للطلاب والإدارة) أو عطّله.</p>
        <div className="flex flex-wrap gap-2">
          {(['off', 'rules', 'claude'] as const).map((m) => (
            <button key={m}
              onClick={() => void changeAssistantMode(m)}
              className={`chip ${assistantMode === m ? 'chip-active' : ''}`}>
              {m === 'off' ? t('admin.modeOff') : m === 'rules' ? t('admin.modeRules') : t('admin.modeClaude')}
            </button>
          ))}
        </div>
      </div>

      {/* Admin analyst chat — only when the assistant is enabled */}
      {assistantMode !== 'off' && (
        <div className="card">
          <strong className="text-slate-900">{t('assistant.analyst')}</strong>
          <div className="mt-3 h-80">
            <ChatPanel
              endpoint="/admin/assistant/chat"
              placeholder={t('assistant.askAdmin')}
              intro={t('assistant.adminIntro')}
            />
          </div>
        </div>
      )}

      <div className="card">
        <div className="mb-3 flex items-center justify-between">
          <strong className="text-slate-900">{t('admin.contactMessages')}</strong>
          <span className="badge">{messages.length}</span>
        </div>
        {messages.length === 0 ? (
          <p className="text-sm text-slate-500">لا توجد رسائل جديدة.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {messages.map((m) => (
              <li key={m.id} className="flex flex-wrap items-start justify-between gap-3 py-3">
                <div className="min-w-0">
                  <p className="text-sm font-bold text-slate-900">
                    {m.subject}
                    <span className="ms-2 text-xs font-medium text-slate-400">
                      {m.name} — <a className="text-xs" href={`mailto:${m.email}`}>{m.email}</a>
                    </span>
                  </p>
                  <p className="mt-1 line-clamp-2 text-sm text-slate-500">{m.message}</p>
                  {m.created_at && <time className="text-xs text-slate-400">{formatDate(m.created_at)}</time>}
                </div>
                <button className="btn btn-ghost shrink-0" onClick={() => void markHandled(m.id)}>
                  {t('admin.markHandled')}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  );
}
