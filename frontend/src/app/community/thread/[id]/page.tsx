'use client';

/**
 * D2 — عرض موضوع المنتدى
 * يشمل:
 *  - زرّ «متابعة / إلغاء المتابعة» بـ aria-pressed في رأس الموضوع
 *  - زرّ «تمييز كإجابة مقبولة» لكل رد (يظهر فقط إن can_accept)
 *  - شارة «إجابة مقبولة» مرئية + نصّية على الرد المميَّز
 *  - تحديث الحالة محليّاً بعد كل استدعاء ناجح
 *  - حالات تحميل / خطأ / فراغ عبر StatusMessage
 *  - RTL، خط Tajawal، تباين AA، a11y كاملة
 */

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { ForumPost, ThreadDetail, AcceptPostResponse, SubscribeResponse } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';
import { useAuth } from '@/lib/auth';
import { EmptyState } from '@/components/EmptyState';

// -------------------------------------------------------
// أيقونة الاختيار — مزيّنة aria-hidden — تباين AA
// -------------------------------------------------------
function CheckCircleIcon({ className }: { className?: string }) {
  return (
    <svg
      aria-hidden="true"
      focusable="false"
      viewBox="0 0 24 24"
      fill="none"
      className={className}
      width="18"
      height="18"
    >
      <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2" />
      <path
        d="M7.5 12.5l3 3 6-6"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

// -------------------------------------------------------
// أيقونة الجرس — مزيّنة aria-hidden
// -------------------------------------------------------
function BellIcon({ className }: { className?: string }) {
  return (
    <svg
      aria-hidden="true"
      focusable="false"
      viewBox="0 0 24 24"
      fill="none"
      className={className}
      width="18"
      height="18"
    >
      <path
        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 00-5-5.917V4a1 1 0 00-2 0v1.083A6 6 0 006 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

// -------------------------------------------------------
// الصفحة الرئيسية
// -------------------------------------------------------
export default function ThreadPage() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();

  // --- بيانات الموضوع ---
  const [thread, setThread] = useState<ThreadDetail['thread'] | null>(null);
  const [posts, setPosts] = useState<ForumPost[]>([]);
  const [subscribed, setSubscribed] = useState(false);
  const [canAccept, setCanAccept] = useState(false);

  // --- حالات واجهة ---
  const [loadError, setLoadError] = useState('');
  const [loading, setLoading] = useState(true);

  // --- نموذج الردّ ---
  const [body, setBody] = useState('');
  const [replying, setReplying] = useState(false);
  const [replyError, setReplyError] = useState('');

  // --- تمييز الإجابة ---
  const [acceptBusy, setAcceptBusy] = useState<number | null>(null); // post_id قيد التحميل
  const [acceptError, setAcceptError] = useState('');
  const [acceptSuccess, setAcceptSuccess] = useState('');

  // --- المتابعة ---
  const [subBusy, setSubBusy] = useState(false);
  const [subError, setSubError] = useState('');
  const [subSuccess, setSubSuccess] = useState('');

  // -------------------------------------------------------
  // تحميل الموضوع والردود
  // -------------------------------------------------------
  function load() {
    setLoading(true);
    setLoadError('');
    api<{ data: ThreadDetail }>(`/community/threads/${id}`)
      .then((r) => {
        setThread(r.data.thread);
        setPosts(r.data.posts);
        setSubscribed(r.data.subscribed);
        setCanAccept(r.data.can_accept);
      })
      .catch(() => setLoadError(t('common.error')))
      .finally(() => setLoading(false));
  }

  useEffect(load, [id]);

  // -------------------------------------------------------
  // إرسال رد جديد
  // -------------------------------------------------------
  async function reply(e: React.FormEvent) {
    e.preventDefault();
    setReplying(true);
    setReplyError('');
    try {
      await api(`/community/threads/${id}/posts`, { method: 'POST', body: { body } });
      setBody('');
      load();
    } catch {
      setReplyError(t('common.error'));
    } finally {
      setReplying(false);
    }
  }

  // -------------------------------------------------------
  // تمييز / إلغاء تمييز الإجابة (toggle)
  // POST /community/threads/{id}/accept { post_id }
  // -------------------------------------------------------
  async function handleAccept(postId: number) {
    if (!thread) return;
    setAcceptBusy(postId);
    setAcceptError('');
    setAcceptSuccess('');
    try {
      const res = await api<AcceptPostResponse>(
        `/community/threads/${id}/accept`,
        { method: 'POST', body: { post_id: postId } }
      );
      // تحديث accepted_post_id محليّاً من الاستجابة
      setThread((prev) =>
        prev ? { ...prev, accepted_post_id: res.data.accepted_post_id } : prev
      );
      setAcceptSuccess(
        res.data.accepted_post_id === null
          ? t('community.unaccept')
          : t('community.acceptedBadge')
      );
    } catch {
      setAcceptError(t('community.acceptError'));
    } finally {
      setAcceptBusy(null);
    }
  }

  // -------------------------------------------------------
  // متابعة / إلغاء المتابعة (toggle)
  // POST /community/threads/{id}/subscribe  → subscribed: true
  // DELETE /community/threads/{id}/subscribe → subscribed: false
  // -------------------------------------------------------
  async function handleSubscribe() {
    setSubBusy(true);
    setSubError('');
    setSubSuccess('');
    const method = subscribed ? 'DELETE' : 'POST';
    try {
      const res = await api<SubscribeResponse>(
        `/community/threads/${id}/subscribe`,
        { method }
      );
      setSubscribed(res.data.subscribed);
      setSubSuccess(
        res.data.subscribed
          ? t('community.subscribed')
          : t('community.unsubscribe')
      );
    } catch {
      setSubError(t('community.subscribeError'));
    } finally {
      setSubBusy(false);
    }
  }

  // -------------------------------------------------------
  // العرض: حالة التحميل
  // -------------------------------------------------------
  if (loading) {
    return (
      <section className="mx-auto max-w-3xl">
        <p className="label mt-10 text-center" aria-live="polite">
          {t('common.loading')}
        </p>
      </section>
    );
  }

  // -------------------------------------------------------
  // العرض: حالة الخطأ
  // -------------------------------------------------------
  if (loadError || !thread) {
    return (
      <section className="mx-auto max-w-3xl">
        <ErrorMsg msg={loadError || t('common.error')} />
      </section>
    );
  }

  // -------------------------------------------------------
  // العرض الرئيسي
  // -------------------------------------------------------
  return (
    <section className="mx-auto max-w-3xl">
      {/* ---- رأس الصفحة + زرّ المتابعة ---- */}
      <div className="mb-5 flex items-start gap-3">
        <div className="flex-1 min-w-0">
          <PageHeader
            title={thread.title}
            crumbs={[{ label: t('community.title') }]}
          />
        </div>

        {/* زرّ متابعة / إلغاء المتابعة — يظهر فقط للمستخدم المصادَق */}
        {user && (
          <div className="mt-1 shrink-0">
            <button
              type="button"
              aria-pressed={subscribed}
              aria-label={subscribed ? t('community.unsubscribe') : t('community.subscribe')}
              title={subscribed ? t('community.subscribed') : t('community.subscribe')}
              disabled={subBusy}
              onClick={handleSubscribe}
              className={[
                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600',
                subscribed
                  ? 'border-brand-600 bg-brand-50 text-brand-700 hover:bg-brand-100'
                  : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                subBusy ? 'opacity-60 cursor-not-allowed' : '',
              ].join(' ')}
            >
              <BellIcon
                className={subscribed ? 'text-brand-600' : 'text-slate-500'}
              />
              <span>{subscribed ? t('community.unsubscribe') : t('community.subscribe')}</span>
            </button>
          </div>
        )}
      </div>

      {/* رسائل حالة المتابعة */}
      <SuccessMsg msg={subSuccess} />
      <ErrorMsg msg={subError} />

      {/* رسائل حالة التمييز */}
      <SuccessMsg msg={acceptSuccess} />
      <ErrorMsg msg={acceptError} />

      {/* ---- قائمة الردود ---- */}
      {posts.length === 0 ? (
        <EmptyState text="لا توجد ردود بعد — كن أول من يُجيب." />
      ) : (
        <div className="space-y-3">
          {posts.map((p, i) => {
            const isAccepted = thread.accepted_post_id === p.id;
            const isAccepting = acceptBusy === p.id;

            return (
              <article
                key={p.id}
                /* تمييز بصري واضح للرد المقبول: إطار أخضر + خلفية فاتحة */
                className={[
                  'card mb-0 flex gap-3',
                  isAccepted
                    ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-400'
                    : '',
                ].join(' ')}
                aria-label={
                  isAccepted
                    ? `${t('community.acceptedBadge')} — ${i === 0 ? 'صاحب الموضوع' : 'مشارك'}`
                    : i === 0
                    ? 'صاحب الموضوع'
                    : 'مشارك'
                }
              >
                {/* رمز الدور */}
                <span
                  aria-hidden="true"
                  className={[
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full font-extrabold',
                    isAccepted
                      ? 'bg-emerald-100 text-emerald-700'
                      : 'bg-brand-100 text-brand-700',
                  ].join(' ')}
                >
                  {i === 0 ? 'س' : 'ر'}
                </span>

                <div className="min-w-0 flex-1">
                  {/* سطر المعلومات + شارة الإجابة المقبولة */}
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-xs font-bold text-slate-600">
                      {i === 0 ? 'صاحب الموضوع' : 'مشارك'}
                    </span>
                    {p.created_at && (
                      <time className="text-xs text-slate-500">
                        {formatDate(p.created_at)}
                      </time>
                    )}

                    {/* شارة «إجابة مقبولة» — نصّ + أيقونة (لا لون فقط) */}
                    {isAccepted && (
                      <span
                        role="status"
                        className="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2.5 py-0.5 text-xs font-semibold text-white"
                        /* تباين AA: white على emerald-600 ~4.5:1 */
                      >
                        <CheckCircleIcon className="text-white" />
                        {t('community.acceptedBadge')}
                      </span>
                    )}
                  </div>

                  {/* نصّ الرد */}
                  <p className="mt-1.5 whitespace-pre-line text-sm leading-7 text-slate-700">
                    {p.body}
                  </p>

                  {/* زرّ التمييز — يظهر فقط إن can_accept وليس الرد الأول (السؤال) */}
                  {canAccept && i > 0 && (
                    <div className="mt-2">
                      <button
                        type="button"
                        disabled={isAccepting}
                        aria-pressed={isAccepted}
                        aria-label={
                          isAccepted
                            ? t('community.unaccept')
                            : t('community.accept')
                        }
                        onClick={() => handleAccept(p.id)}
                        className={[
                          'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition',
                          'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600',
                          isAccepted
                            ? 'border-emerald-500 bg-emerald-600 text-white hover:bg-emerald-700'
                            : 'border-slate-300 bg-white text-slate-600 hover:border-emerald-400 hover:text-emerald-700',
                          isAccepting ? 'opacity-60 cursor-not-allowed' : '',
                        ].join(' ')}
                      >
                        <CheckCircleIcon
                          className={isAccepted ? 'text-white' : 'text-slate-400'}
                        />
                        <span>
                          {isAccepting
                            ? t('common.loading')
                            : isAccepted
                            ? t('community.unaccept')
                            : t('community.accept')}
                        </span>
                      </button>
                    </div>
                  )}
                </div>
              </article>
            );
          })}
        </div>
      )}

      {/* ---- نموذج إرسال رد ---- */}
      <form className="card mt-5" onSubmit={reply} aria-label="نموذج الرد">
        <label className="label" htmlFor="reply-body">
          {t('community.reply')}
        </label>
        <textarea
          id="reply-body"
          className="input min-h-24"
          value={body}
          onChange={(e) => setBody(e.target.value)}
          required
          disabled={replying}
          aria-describedby={replyError ? 'reply-error' : undefined}
        />
        {replyError && (
          <p id="reply-error" role="alert" className="error mb-2">
            {replyError}
          </p>
        )}
        <button className="btn" disabled={replying}>
          {replying ? t('common.loading') : t('community.send')}
        </button>
      </form>
    </section>
  );
}
