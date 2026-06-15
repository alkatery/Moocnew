'use client';

/**
 * C3 — مكوّن التواصل في الاستوديو
 *
 * قسمان:
 * ١. نشر إعلان: POST /api/v1/courses/{slug}/announcements (201)
 *    + قائمة الإعلانات السابقة: GET /api/v1/courses/{slug}/announcements
 * ٢. إرسال بريد جماعي: POST /api/v1/courses/{slug}/bulk-email (202)
 *    مع تأكيد window.confirm قبل الإرسال (حاجز ضد الإرسال العَرَضي)
 *
 * a11y: كل حقل معنون بـ <label htmlFor>، ErrorMsg role="alert"،
 *       SuccessMsg role="status"، تباين AA (slate-500+)، RTL موروث، Tajawal موروث.
 */

import { useCallback, useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { t } from '@/i18n/dictionary';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';
import type {
  AnnouncementCreateResponse,
  AnnouncementsListResponse,
  BulkEmailResponse,
  CourseAnnouncement,
} from '@/lib/types';

// ─────────────────────────────────────────────────────────────────────────────
// المكوّن الرئيسي
// ─────────────────────────────────────────────────────────────────────────────

export function CommunicationsPanel({ courseSlug }: { courseSlug: string }) {
  return (
    <div className="grid gap-6 lg:grid-cols-2">
      {/* ── القسم الأول: الإعلانات ── */}
      <div className="space-y-6 self-start">
        <AnnouncementForm courseSlug={courseSlug} />
        <AnnouncementList courseSlug={courseSlug} />
      </div>

      {/* ── القسم الثاني: البريد الجماعي ── */}
      <div className="self-start">
        <BulkEmailForm courseSlug={courseSlug} />
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// نموذج نشر الإعلان
// ─────────────────────────────────────────────────────────────────────────────

function AnnouncementForm({ courseSlug }: { courseSlug: string }) {
  const [title, setTitle]       = useState('');
  const [body, setBody]         = useState('');
  const [busy, setBusy]         = useState(false);
  const [err, setErr]           = useState('');
  const [success, setSuccess]   = useState('');

  // حدث يُطلَق عند نجاح النشر لإعادة تحميل القائمة
  const dispatchRefresh = () =>
    window.dispatchEvent(new CustomEvent('comm:announcement:refresh'));

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (busy) return;
    setErr('');
    setSuccess('');
    setBusy(true);

    try {
      const res = await api<AnnouncementCreateResponse>(
        `/courses/${courseSlug}/announcements`,
        { method: 'POST', body: { title, body } },
      );
      // رسالة النجاح مع عدد المتعلّمين المُبلَّغين (§5 من العقد)
      setSuccess(
        `${t('comm.announce.success')} — أُبلغ ${res.recipients_queued} متعلّماً`,
      );
      setTitle('');
      setBody('');
      dispatchRefresh();
    } catch (e) {
      const status = (e as { status?: number })?.status;
      if (status === 429) {
        setErr(t('comm.rateLimited'));
      } else if (status === 422) {
        // رسالة التحقّق من الخادم
        const detail = (e as { message?: string })?.message;
        setErr(detail ?? t('comm.error'));
      } else {
        setErr(t('comm.error'));
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="card mb-0">
      {/* عنوان القسم */}
      <strong className="text-slate-900">{t('comm.announce.title')}</strong>
      <p className="mb-4 text-xs text-slate-500">
        يظهر للمتعلّمين الملتحقين داخل التطبيق وعبر البريد (حسب تفضيلاتهم).
      </p>

      {/* رسائل الحالة */}
      {err     && <ErrorMsg   msg={err}     />}
      {success && <SuccessMsg msg={success} />}

      <form
        className="space-y-4"
        onSubmit={(e) => void handleSubmit(e)}
        aria-label={t('comm.announce.title')}
      >
        {/* حقل العنوان */}
        <div>
          <label
            htmlFor="ann-title"
            className="label mb-1 block text-sm font-medium text-slate-700"
          >
            {t('comm.announce.fieldTitle')}
            <span aria-hidden="true" className="ms-1 text-red-500">*</span>
          </label>
          <input
            id="ann-title"
            className="input"
            type="text"
            required
            maxLength={255}
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="مثال: موعد الاختبار النهائي"
            disabled={busy}
            aria-required="true"
          />
        </div>

        {/* حقل النصّ */}
        <div>
          <label
            htmlFor="ann-body"
            className="label mb-1 block text-sm font-medium text-slate-700"
          >
            {t('comm.announce.body')}
            <span aria-hidden="true" className="ms-1 text-red-500">*</span>
          </label>
          <textarea
            id="ann-body"
            className="input min-h-[120px]"
            required
            maxLength={5000}
            value={body}
            onChange={(e) => setBody(e.target.value)}
            placeholder="نصّ الإعلان الموجَّه للمتعلّمين…"
            disabled={busy}
            aria-required="true"
          />
          <p className="mt-1 text-xs text-slate-500">
            {body.length} / 5000 حرف
          </p>
        </div>

        <button
          className="btn w-full"
          type="submit"
          disabled={busy}
          aria-busy={busy}
        >
          {busy ? t('common.loading') : t('comm.announce.submit')}
        </button>
      </form>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// قائمة الإعلانات السابقة
// ─────────────────────────────────────────────────────────────────────────────

function AnnouncementList({ courseSlug }: { courseSlug: string }) {
  const [items, setItems]   = useState<CourseAnnouncement[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr]       = useState('');

  const load = useCallback(() => {
    setLoading(true);
    setErr('');
    api<AnnouncementsListResponse>(`/courses/${courseSlug}/announcements`)
      .then((r) => {
        setItems(r.data);
        setLoading(false);
      })
      .catch(() => {
        setErr(t('comm.error'));
        setLoading(false);
      });
  }, [courseSlug]);

  useEffect(() => {
    load();
    // إعادة التحميل عند نشر إعلان جديد (حدث مُطلَق من AnnouncementForm)
    const handler = () => load();
    window.addEventListener('comm:announcement:refresh', handler);
    return () => window.removeEventListener('comm:announcement:refresh', handler);
  }, [load]);

  return (
    <div className="card mb-0">
      <strong className="text-slate-900">الإعلانات السابقة</strong>

      {/* حالة التحميل */}
      {loading && (
        <p className="mt-3 text-sm text-slate-500" aria-live="polite">
          {t('common.loading')}
        </p>
      )}

      {/* حالة الخطأ */}
      {!loading && err && <ErrorMsg msg={err} />}

      {/* حالة الفراغ */}
      {!loading && !err && items.length === 0 && (
        <p className="mt-3 text-sm text-slate-500">
          {t('comm.announce.empty')}
        </p>
      )}

      {/* قائمة الإعلانات */}
      {!loading && !err && items.length > 0 && (
        <ul className="mt-3 divide-y divide-slate-100" aria-label="الإعلانات السابقة">
          {items.map((ann) => (
            <li key={ann.id} className="py-3">
              {/* عنوان الإعلان */}
              <p className="text-sm font-semibold text-slate-800">{ann.title}</p>

              {/* نصّ الإعلان — عرض كنصّ عادي (لا HTML خام — §6 من العقد) */}
              <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">
                {ann.body}
              </p>

              {/* بيانات ميتا: المؤلّف والتاريخ */}
              <p className="mt-1.5 text-xs text-slate-500">
                {ann.author.name}
                {' · '}
                <time dateTime={ann.created_at}>
                  {new Date(ann.created_at).toLocaleString('ar-SA', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                  })}
                </time>
              </p>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// نموذج البريد الجماعي
// ─────────────────────────────────────────────────────────────────────────────

function BulkEmailForm({ courseSlug }: { courseSlug: string }) {
  const [subject, setSubject] = useState('');
  const [body, setBody]       = useState('');
  const [busy, setBusy]       = useState(false);
  const [err, setErr]         = useState('');
  const [success, setSuccess] = useState('');

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (busy) return;
    setErr('');
    setSuccess('');

    // ── تأكيد قبل الإرسال (§5 من العقد — حاجز ضد الإرسال العَرَضي) ──
    const confirmed = window.confirm(t('comm.email.confirm'));
    if (!confirmed) return;

    setBusy(true);

    try {
      const res = await api<BulkEmailResponse>(
        `/courses/${courseSlug}/bulk-email`,
        {
          method: 'POST',
          // audience اختياري — الافتراضي all_active (§3.ج من العقد)
          body: { subject, body, audience: 'all_active' },
        },
      );
      // رسالة النجاح مع عدد المتعلّمين المُدرَجين في الطابور (202 Accepted)
      setSuccess(
        `${t('comm.email.queued')} (${res.recipients_queued})`,
      );
      setSubject('');
      setBody('');
    } catch (e) {
      const status = (e as { status?: number })?.status;
      if (status === 429) {
        setErr(t('comm.rateLimited'));
      } else if (status === 422) {
        const detail = (e as { message?: string })?.message;
        setErr(detail ?? t('comm.error'));
      } else {
        setErr(t('comm.error'));
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="card mb-0">
      {/* عنوان القسم */}
      <strong className="text-slate-900">{t('comm.email.title')}</strong>
      <p className="mb-4 text-xs text-slate-500">
        يُرسَل بريد إلكتروني لكل المتعلّمين النشطين (عبر الطابور، يحترم تفضيلات opt-out لكل متعلّم).
      </p>

      {/* رسائل الحالة */}
      {err     && <ErrorMsg   msg={err}     />}
      {success && <SuccessMsg msg={success} />}

      {/* منتقي الفئة — ثابت في v1 (all_active فقط) */}
      <div className="mb-4">
        <label
          htmlFor="email-audience"
          className="label mb-1 block text-sm font-medium text-slate-700"
        >
          الفئة المستهدَفة
        </label>
        <select
          id="email-audience"
          className="input"
          value="all_active"
          disabled
          aria-readonly="true"
          aria-label="الفئة المستهدَفة — كل المتعلّمين النشطين"
        >
          <option value="all_active">{t('comm.audience.allActive')}</option>
        </select>
        <p className="mt-1 text-xs text-slate-500">
          v1: الفئة الوحيدة المدعومة هي كل المتعلّمين النشطين.
        </p>
      </div>

      <form
        className="space-y-4"
        onSubmit={(e) => void handleSubmit(e)}
        aria-label={t('comm.email.title')}
      >
        {/* حقل الموضوع */}
        <div>
          <label
            htmlFor="email-subject"
            className="label mb-1 block text-sm font-medium text-slate-700"
          >
            {t('comm.email.subject')}
            <span aria-hidden="true" className="ms-1 text-red-500">*</span>
          </label>
          <input
            id="email-subject"
            className="input"
            type="text"
            required
            maxLength={255}
            value={subject}
            onChange={(e) => setSubject(e.target.value)}
            placeholder="مثال: تذكير بموعد تسليم الواجب"
            disabled={busy}
            aria-required="true"
          />
        </div>

        {/* حقل النصّ */}
        <div>
          <label
            htmlFor="email-body"
            className="label mb-1 block text-sm font-medium text-slate-700"
          >
            {t('comm.email.body')}
            <span aria-hidden="true" className="ms-1 text-red-500">*</span>
          </label>
          <textarea
            id="email-body"
            className="input min-h-[160px]"
            required
            maxLength={5000}
            value={body}
            onChange={(e) => setBody(e.target.value)}
            placeholder="نصّ البريد الإلكتروني الموجَّه للمتعلّمين… (نصّ عادي)"
            disabled={busy}
            aria-required="true"
          />
          <p className="mt-1 text-xs text-slate-500">
            {body.length} / 5000 حرف — نصّ عادي فقط (لا رموز HTML).
          </p>
        </div>

        {/* تحذير واضح قبل الإرسال */}
        <div
          className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
          role="note"
          aria-label="تنبيه: إرسال جماعي"
        >
          سيُرسَل هذا البريد لجميع المتعلّمين النشطين في الدورة. ستُطلَب منك موافقة قبل الإرسال.
        </div>

        <button
          className="btn w-full"
          type="submit"
          disabled={busy}
          aria-busy={busy}
        >
          {busy ? t('common.loading') : t('comm.email.submit')}
        </button>
      </form>
    </div>
  );
}
