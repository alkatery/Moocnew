'use client';

// جزيرة العميل: تحوي كل JSX التفاعلي ومنطق join/startCourse/busy/msg/useAuth/useRouter.
// المسار يأتي كـ prop جاهزاً من الغلاف الخادمي (قيم زائر للـ viewer — مقصود، §3.1).
// بعد نجاح join/startCourse تُعاد الجلب عميلاً بـ auth:true لتحديث viewer/الحالة.
// العقد: B2-content-seo.md §3.1

import Link from 'next/link';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { PathDetail, PathLevelItem } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { ErrorMsg } from '@/components/StatusMessage';

// ---------------------------------------------------------------------------
// أيقونة حالة العنصر: مكتمل / مفتوح / مقفل
// ---------------------------------------------------------------------------
function StateIcon({ state }: { state: PathLevelItem['state'] }) {
  if (state === 'completed') {
    return (
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="m5 13 4 4 10-10" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </span>
    );
  }
  if (state === 'unlocked') {
    return (
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="M8 6.5 18 12 8 17.5z" fill="currentColor" />
        </svg>
      </span>
    );
  }
  return (
    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden>
        <path d="M7 10V8a5 5 0 1 1 10 0v2m-12 0h14v11H5z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
      </svg>
    </span>
  );
}

// ---------------------------------------------------------------------------
// الجزيرة التفاعلية الرئيسية
// ---------------------------------------------------------------------------
export function PathDetailClient({ path: initialPath }: { path: PathDetail }) {
  const { user } = useAuth();
  const router = useRouter();

  // نسخة محلية من المسار — تُهيّأ من الـ prop (قيم زائر)
  // وتُحدَّث بعد الالتحاق عبر جلب عميلي مصادَق (§3.1).
  const [path, setPath] = useState<PathDetail>(initialPath);
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  // إعادة جلب المسار عميلاً بـ auth:true لتحديث viewer/state بعد الالتحاق
  async function reloadWithAuth() {
    try {
      const res = await api<{ data: PathDetail }>(`/learning/paths/${path.slug}`, { auth: true });
      setPath(res.data);
    } catch {
      // فشل التحديث — نترك الحالة المحلية كما هي
    }
  }

  async function join() {
    if (!user) { router.push('/login'); return; }
    setBusy(true); setMsg('');
    try {
      await api(`/learning/paths/${path.slug}/enroll`, { method: 'POST' });
      await reloadWithAuth();
    } catch (err) {
      setMsg(err instanceof ApiError ? err.message : t('common.error'));
    } finally { setBusy(false); }
  }

  async function startCourse(item: PathLevelItem) {
    if (!user) { router.push('/login'); return; }
    setMsg('');
    try {
      const res = await api<{ data: { status: string } }>(
        `/learning/paths/${path.slug}/courses/${item.course.slug}/enroll`,
        { method: 'POST' },
      );
      router.push(res.data.status === 'pending' ? `/checkout/${item.course.slug}` : `/learn/${item.course.slug}`);
    } catch (err) {
      setMsg(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  const progress = path.viewer.progress;

  return (
    <section>
      {/* G3: text-slate-400 → text-slate-500 على روابط breadcrumb (خلفية بيضاء) */}
      <nav className="mb-4 flex flex-wrap items-center gap-1.5 text-xs text-slate-500" aria-label="مسار التنقّل">
        <Link className="text-slate-500 hover:text-brand-600" href="/">الرئيسية</Link>
        <span aria-hidden>‹</span>
        <Link className="text-slate-500 hover:text-brand-600" href="/paths">{t('paths.title')}</Link>
        <span aria-hidden>‹</span>
        <span className="font-medium text-slate-500">{path.title}</span>
      </nav>

      {/* Hero */}
      <div className="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-900 via-brand-700 to-brand-500 p-8 text-white shadow-card sm:p-10">
        <div className="pointer-events-none absolute -bottom-24 -start-10 h-64 w-64 rounded-full bg-white/10 blur-3xl" aria-hidden />
        <div className="relative max-w-2xl">
          <div className="flex flex-wrap gap-2">
            <span className="badge bg-white/15 text-white">مسار تخصصي</span>
            <span className="badge bg-white/15 text-white">{path.levels.length} مستويات</span>
            <span className="badge bg-white/15 text-white">شهادة مسار موثّقة</span>
          </div>
          <h1 className="mt-4 text-3xl font-extrabold leading-snug text-white sm:text-4xl">{path.title}</h1>
          <p className="mt-3 leading-8 text-brand-50/90">{path.description ?? path.summary}</p>

          {path.viewer.completed ? (
            <div className="mt-5 rounded-xl bg-emerald-500/20 px-4 py-3 text-sm font-semibold text-emerald-100">
              🎉 {t('paths.completed')}{' '}
              <Link className="text-white underline" href="/certificates">{t('nav.certificates')}</Link>
            </div>
          ) : path.viewer.enrolled && progress ? (
            <div className="mt-5 max-w-md">
              <div className="mb-1 flex items-center justify-between text-sm text-brand-100">
                <span>{t('paths.joined')} — {progress.completed}/{progress.total} دورات</span>
                <span className="font-bold text-white">{progress.percent}%</span>
              </div>
              <div className="h-2 overflow-hidden rounded-full bg-white/20">
                <span className="block h-full rounded-full bg-white" style={{ width: `${progress.percent}%` }} />
              </div>
            </div>
          ) : (
            <button className="btn mt-5 bg-white px-6 text-brand-700 hover:bg-brand-50" onClick={() => void join()} disabled={busy}>
              {busy ? t('common.loading') : t('paths.join')}
            </button>
          )}
        </div>
      </div>

      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={msg} />
      <p className="mb-6 flex items-center gap-2 text-sm text-slate-500">
        <svg className="text-brand-500" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="M12 9v4m0 4h.01M12 3l10 18H2z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" />
        </svg>
        {t('paths.certificateNote')}
      </p>

      {/* Levels */}
      <div className="space-y-6">
        {path.levels.map((level) => (
          <div key={level.level}>
            <div className="mb-3 flex items-center gap-3">
              <span className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-extrabold text-white">
                {level.level}
              </span>
              <h2 className="text-xl font-extrabold">{t('paths.level')} {level.level}</h2>
              <span className="text-xs text-slate-500">{level.items.length} دورات</span>
            </div>
            <div className="card p-0">
              <ul className="divide-y divide-slate-100">
                {level.items.map((item) => (
                  <li key={item.course.id} className={`flex flex-wrap items-center gap-3 px-5 py-4 ${item.state === 'locked' ? 'opacity-60' : ''}`}>
                    <StateIcon state={item.state} />
                    <div className="min-w-0 flex-1">
                      <strong className="block text-slate-900">{item.course.title}</strong>
                      <p className="line-clamp-1 text-sm text-slate-500">{item.course.summary}</p>
                      <span className="text-xs text-slate-500">
                        {item.course.instructor && `${item.course.instructor} · `}
                        {item.course.pricing_type === 'free' ? t('course.free') : formatMinor(item.course.price_minor)}
                      </span>
                    </div>
                    {item.state === 'completed' && (
                      <Link className="btn btn-ghost shrink-0" href={`/learn/${item.course.slug}`}>{t('paths.review')}</Link>
                    )}
                    {item.state === 'unlocked' && (
                      <button className="btn shrink-0" onClick={() => void startCourse(item)}>{t('paths.startCourse')}</button>
                    )}
                    {item.state === 'locked' && (
                      <span className="text-xs font-semibold text-slate-500">{t('paths.locked')}</span>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
