'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { SurveyCourseSummary, SurveyDetailSummary } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { Stars } from '@/components/Stars';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';

const AXIS_LABELS: { key: keyof SurveyCourseSummary['averages']; label: string }[] = [
  { key: 'overall', label: 'عام' },
  { key: 'content_quality', label: 'المحتوى' },
  { key: 'instructor_quality', label: 'المدرّب' },
  { key: 'platform_quality', label: 'المنصة' },
];

export default function AdminQualityPage() {
  const [courses, setCourses] = useState<SurveyCourseSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<SurveyCourseSummary | null>(null);
  const [detail, setDetail] = useState<SurveyDetailSummary | null>(null);
  const [license, setLicense] = useState('');
  const [licenseSaved, setLicenseSaved] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ data: SurveyCourseSummary[] }>('/admin/surveys/summary')
      .then((r) => setCourses(r.data))
      .catch(() => setError(t('common.error')))
      .finally(() => setLoading(false));
    api<{ nelc_license_number: string | null }>('/admin/settings')
      .then((r) => setLicense(r.nelc_license_number ?? ''))
      .catch(() => undefined);
  }, []);

  async function openDetails(course: SurveyCourseSummary) {
    setSelected(course);
    setDetail(null);
    try {
      const r = await api<{ data: SurveyDetailSummary }>(`/admin/surveys/summary?course_id=${course.course_id}`);
      setDetail(r.data);
    } catch {
      setError(t('common.error'));
    }
  }

  async function saveLicense() {
    setError('');
    setLicenseSaved(false);
    try {
      await api('/admin/settings/nelc', { method: 'PATCH', body: { license_number: license.trim() || null } });
      setLicenseSaved(true);
      setTimeout(() => setLicenseSaved(false), 2500);
    } catch {
      setError(t('common.error'));
    }
  }

  return (
    <section>
      <PageHeader
        title="جودة التعليم"
        subtitle="ملخص استبيانات رضا المتعلمين لكل دورة منشورة، وبيانات ترخيص المركز الوطني للتعليم الإلكتروني."
        crumbs={[{ label: t('admin.title'), href: '/admin' }, { label: 'جودة التعليم' }]}
        actions={<Link className="btn btn-ghost" href="/admin">{t('admin.title')}</Link>}
      />
      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={error} />

      {/* NELC licence */}
      <div className="card">
        <strong className="text-slate-900">بيانات الترخيص</strong>
        <p className="mb-3 text-sm text-slate-500">
          رقم ترخيص المركز الوطني للتعليم الإلكتروني — يُطبع على الشهادات ويظهر في صفحة التحقق العامة.
        </p>
        <div className="flex flex-wrap items-center gap-3">
          <input
            className="input max-w-sm"
            dir="ltr"
            placeholder="مثال: NELC-L-2026-001"
            value={license}
            maxLength={100}
            onChange={(e) => setLicense(e.target.value)}
          />
          <button className="btn" onClick={() => void saveLicense()}>{t('common.save')}</button>
          {/* G5: role="status" لرسالة نجاح الحفظ */}
          <SuccessMsg msg={licenseSaved ? 'تم الحفظ.' : ''} />
        </div>
      </div>

      {/* Per-course survey summary */}
      <div className="card">
        <div className="mb-3 flex items-center justify-between">
          <strong className="text-slate-900">استبيانات رضا المتعلمين</strong>
          <span className="badge">{courses.length} دورة</span>
        </div>

        {loading ? (
          <p className="label">{t('common.loading')}</p>
        ) : courses.length === 0 ? (
          <p className="text-sm text-slate-500">لا توجد دورات منشورة بعد.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {courses.map((c) => (
              <li key={c.course_id} className="py-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="min-w-0">
                    <strong className="block text-sm text-slate-900">{c.title}</strong>
                    <span className="text-xs text-slate-500">{c.count} استجابة</span>
                  </div>
                  <div className="flex flex-wrap items-center gap-4">
                    {AXIS_LABELS.map((axis) => (
                      <span key={axis.key} className="flex items-center gap-1 text-xs text-slate-500">
                        {axis.label}
                        {c.count > 0 ? (
                          <>
                            <Stars value={c.averages[axis.key]} size={13} />
                            <b className="text-slate-700">{c.averages[axis.key].toFixed(1)}</b>
                          </>
                        ) : (
                          <span className="text-slate-300">—</span>
                        )}
                      </span>
                    ))}
                    <button
                      className="btn btn-ghost"
                      disabled={c.count === 0}
                      onClick={() => void openDetails(c)}>
                      التعليقات
                    </button>
                  </div>
                </div>

                {selected?.course_id === c.course_id && (
                  <div className="mt-3 rounded-xl bg-slate-50 p-4">
                    {!detail ? (
                      <p className="label">{t('common.loading')}</p>
                    ) : detail.comments.length === 0 ? (
                      <p className="text-sm text-slate-500">لا توجد تعليقات نصية بعد.</p>
                    ) : (
                      <ul className="space-y-3">
                        {detail.comments.map((comment, i) => (
                          <li key={i} className="text-sm leading-7 text-slate-600">
                            «{comment.comment}»
                            {comment.created_at && (
                              <time className="ms-2 text-xs text-slate-500">{formatDate(comment.created_at)}</time>
                            )}
                          </li>
                        ))}
                      </ul>
                    )}
                    <p className="mt-3 text-xs text-slate-500">تُعرض التعليقات دون هوية أصحابها التزاماً بنظام حماية البيانات الشخصية.</p>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  );
}
