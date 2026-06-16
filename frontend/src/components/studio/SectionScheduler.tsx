/**
 * E2 — مكوّن جدولة ظهور القسم (SectionScheduler)
 *
 * يُستخدَم داخل تبويب «المنهج» في الاستوديو لكل قسم.
 * يعرض شارة حالة الظهور، ويتيح ضبط visible_from أو إلغاءه
 * عبر PATCH /catalog/sections/{id} وفق العقد E2 §4.ب.
 *
 * حالات: ظاهر الآن (null أو ماضٍ) | مجدول (مستقبل) | جارٍ الحفظ | خطأ | نجاح
 * a11y: label لحقل التاريخ، aria-label للأزرار، ErrorMsg/SuccessMsg، RTL موروث
 */

'use client';

import { useState } from 'react';
import { api } from '@/lib/api';
import type { Section } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { formatDate } from '@/lib/format';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';

interface Props {
  /** القسم الحالي (يحمل visible_from وid وtitle) */
  section: Section;
  /** يُستدعى بعد حفظ ناجح لإعادة جلب بيانات المقرر */
  onSaved: () => void;
}

/**
 * يحسب حالة الظهور بناءً على visible_from:
 *  - null         → ظاهر دائماً
 *  - <= now()     → ظاهر (صدر)
 *  - > now()      → مجدول للمستقبل
 */
function visibilityState(visible_from: string | null | undefined): 'visible' | 'scheduled' {
  if (!visible_from) return 'visible';
  return new Date(visible_from) > new Date() ? 'scheduled' : 'visible';
}

/**
 * يحوّل قيمة datetime-local (بالمنطقة الزمنية المحلية) إلى ISO 8601 مع offset.
 * المتصفّح يُعيد "YYYY-MM-DDTHH:mm" بلا منطقة زمنية، لذا نُكمل الإرسال بـ toISOString().
 */
function localToISO(localVal: string): string {
  // new Date(localVal) يُفسّرها كتوقيت محلي (تسلوك مقبول — العقد يقبل timestamp صالح مع offset)
  return new Date(localVal).toISOString();
}

export function SectionScheduler({ section, onSaved }: Props) {
  // إظهار/إخفاء حقل الإدخال
  const [open, setOpen] = useState(false);
  // قيمة datetime-local (نفس ما يقرأه المتصفّح)
  const [dateVal, setDateVal] = useState<string>(() => {
    if (section.visible_from) {
      // نحوّل ISO → تنسيق datetime-local "YYYY-MM-DDTHH:mm"
      try {
        const d = new Date(section.visible_from);
        // slice(0,16) يأخذ "YYYY-MM-DDTHH:mm" من ISO string (UTC) — يُحوَّل للمحلي أسفل
        const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 16);
      } catch { return ''; }
    }
    return '';
  });
  const [saving, setSaving] = useState(false);
  const [errMsg, setErrMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  const state = visibilityState(section.visible_from);

  /** يُرسل PATCH بـ visible_from أو null */
  async function patchVisibleFrom(value: string | null) {
    setErrMsg('');
    setSuccessMsg('');
    setSaving(true);
    try {
      await api(`/catalog/sections/${section.id}`, {
        method: 'PATCH',
        // إرسال null صريح لإلغاء الجدولة — العقد §1.ب يضمن أن null يُصفَّر الحقل
        body: { visible_from: value },
      });
      // نجاح: رسالة مناسبة، إغلاق المحرر، إعادة تحميل
      setSuccessMsg(value ? t('scheduled.savedVisible') : t('scheduled.clearSuccess'));
      setOpen(false);
      onSaved();
    } catch {
      setErrMsg(t('scheduled.error'));
    } finally {
      setSaving(false);
    }
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    if (!dateVal) {
      // تاريخ فارغ = إلغاء الجدولة (ظاهر فوراً)
      await patchVisibleFrom(null);
      return;
    }
    await patchVisibleFrom(localToISO(dateVal));
  }

  async function handleClear() {
    setDateVal('');
    await patchVisibleFrom(null);
  }

  return (
    <div className="mt-1">
      {/* شارة حالة الظهور — نصّية (لا اعتماد على اللون وحده) */}
      {state === 'scheduled' && section.visible_from ? (
        <span
          className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200"
          aria-label={t('scheduled.visibleOn').replace('{date}', formatDate(section.visible_from))}
        >
          {/* أيقونة ساعة بسيطة (SVG inline — بلا اعتماد على مكتبة أيقونات خارجية) */}
          <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
          </svg>
          {t('scheduled.visibleOn').replace('{date}', formatDate(section.visible_from))}
        </span>
      ) : (
        <span
          className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"
          aria-label={t('scheduled.visible')}
        >
          <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          {t('scheduled.visible')}
        </span>
      )}

      {/* زرّ فتح محرر الجدولة */}
      <button
        type="button"
        className="ms-2 rounded px-1.5 py-0.5 text-xs text-brand-600 hover:bg-brand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600"
        aria-label={`${t('scheduled.schedule')}: ${section.title}`}
        aria-expanded={open}
        onClick={() => { setOpen((v) => !v); setErrMsg(''); setSuccessMsg(''); }}
      >
        {open ? '▲' : '▼'} {t('scheduled.schedule')}
      </button>

      {/* رسائل الحالة خارج المحرر لتكون ظاهرة دائماً */}
      <ErrorMsg msg={errMsg} />
      <SuccessMsg msg={successMsg} />

      {/* المحرر المنبثق — يظهر فقط عند open */}
      {open && (
        <form
          onSubmit={(e) => void handleSave(e)}
          className="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-3"
          aria-label={`إعدادات جدولة القسم: ${section.title}`}
        >
          {/* label مرتبط بحقل التاريخ (a11y) */}
          <label
            htmlFor={`visible-from-${section.id}`}
            className="mb-1 block text-xs font-medium text-slate-700"
          >
            {t('scheduled.fieldLabel')}
          </label>
          <p className="mb-2 text-xs text-slate-500">{t('scheduled.hint')}</p>

          <div className="flex flex-wrap items-center gap-2">
            <input
              id={`visible-from-${section.id}`}
              type="datetime-local"
              className="input m-0 text-sm"
              dir="ltr"
              value={dateVal}
              onChange={(e) => setDateVal(e.target.value)}
              aria-describedby={`visible-from-hint-${section.id}`}
              disabled={saving}
            />

            {/* زرّ الحفظ */}
            <button
              type="submit"
              className="btn shrink-0 text-sm"
              disabled={saving}
              aria-label={`${t('common.save')} موعد ظهور القسم: ${section.title}`}
            >
              {saving ? t('common.loading') : t('common.save')}
            </button>

            {/* زرّ إلغاء الجدولة — يُرسل null */}
            {section.visible_from && (
              <button
                type="button"
                className="btn btn-ghost shrink-0 text-sm text-red-600 hover:bg-red-50"
                disabled={saving}
                aria-label={`${t('scheduled.unschedule')}: ${section.title}`}
                onClick={() => void handleClear()}
              >
                {t('scheduled.unschedule')}
              </button>
            )}
          </div>

          {/* نصّ وصفي مخفي للقارئات (id مرتبط بـ aria-describedby) */}
          <span id={`visible-from-hint-${section.id}`} className="sr-only">
            {t('scheduled.hint')}
          </span>
        </form>
      )}
    </div>
  );
}
