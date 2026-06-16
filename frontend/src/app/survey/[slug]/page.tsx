'use client';

import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { Course, SurveyAnswers } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { Stars } from '@/components/Stars';
import { ErrorMsg } from '@/components/StatusMessage';

const AXES: { key: keyof Omit<SurveyAnswers, 'comment'>; label: string; hint: string }[] = [
  { key: 'overall', label: 'التقييم العام', hint: 'رضاك العام عن تجربة الدورة' },
  { key: 'content_quality', label: 'جودة المحتوى', hint: 'وضوح المادة وحداثتها وتنظيمها' },
  { key: 'instructor_quality', label: 'أداء المدرّب', hint: 'الشرح والتفاعل والإجابة عن الأسئلة' },
  { key: 'platform_quality', label: 'جودة المنصة', hint: 'سهولة الاستخدام وجودة التشغيل' },
];

export default function SurveyPage() {
  const { slug } = useParams<{ slug: string }>();
  const router = useRouter();
  const [course, setCourse] = useState<Course | null>(null);
  const [ratings, setRatings] = useState<Record<string, number>>({
    overall: 0, content_quality: 0, instructor_quality: 0, platform_quality: 0,
  });
  const [comment, setComment] = useState('');
  const [saving, setSaving] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!slug) return;
    void api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((r) => setCourse(r.data)).catch(() => setCourse(null));
  }, [slug]);

  const incomplete = AXES.some((a) => !ratings[a.key]);

  async function submit() {
    setError('');
    if (incomplete) { setError('فضلاً قيّم المحاور الأربعة جميعها.'); return; }
    setSaving(true);
    try {
      await api(`/engagement/courses/${slug}/survey`, {
        method: 'POST',
        body: { ...ratings, comment: comment.trim() || null },
      });
      setDone(true);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t('common.error'));
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="mx-auto max-w-2xl">
      <PageHeader
        title="قيّم تجربتك"
        subtitle={course ? `استبيان الرضا عن دورة «${course.title}» — يساعدنا تقييمك على تطوير جودة التعليم.` : 'استبيان رضا المتعلمين.'}
        crumbs={[{ label: t('learn.title'), href: '/learn' }, { label: 'قيّم تجربتك' }]}
      />

      {done ? (
        <div className="card text-center">
          <p className="success">شكراً لك! تم تسجيل تقييمك بنجاح.</p>
          <div className="mt-4 flex justify-center gap-3">
            <button className="btn btn-ghost" onClick={() => router.push('/learn')}>العودة لدوراتي</button>
            <Link className="btn" href="/certificates">شهاداتي</Link>
          </div>
        </div>
      ) : (
        <div className="card">
          {AXES.map((axis) => (
            <div key={axis.key} className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4 last:border-0">
              <div>
                <span className="label block text-slate-900">{axis.label}</span>
                <span className="text-xs text-slate-500">{axis.hint}</span>
              </div>
              <Stars
                size={26}
                value={ratings[axis.key]}
                onChange={(v) => setRatings((prev) => ({ ...prev, [axis.key]: v }))}
              />
            </div>
          ))}

          <label className="label" htmlFor="survey-comment">ملاحظات إضافية (اختياري)</label>
          <textarea
            id="survey-comment"
            className="input min-h-[120px]"
            maxLength={2000}
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            placeholder="ما الذي أعجبك؟ وما الذي تقترح تحسينه؟"
          />
          <p className="mt-2 text-xs text-slate-500">تُعرض الملاحظات لفريق الجودة دون اسمك حفاظاً على خصوصيتك.</p>

          {/* G5: role="alert" عبر ErrorMsg */}
          <ErrorMsg msg={error} />

          <button className="btn mt-4 w-full" disabled={saving} onClick={() => void submit()}>
            {saving ? t('common.loading') : 'إرسال التقييم'}
          </button>
        </div>
      )}
    </section>
  );
}
