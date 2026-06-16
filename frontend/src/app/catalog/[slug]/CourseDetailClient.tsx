'use client';

// جزيرة العميل: تحوي كل JSX التفاعلي ومنطق enroll/busy/msg/useAuth/useRouter.
// الدورة تأتي كـ prop جاهزة من الغلاف الخادمي — لا جلب هنا ولا حارس تحميل.
// E1: أُضيف منطق المتطلّبات السابقة — حساب محلي من GET /enrollments.

import Link from 'next/link';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Course, CoursePrerequisite, Enrollment } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';
import { Reviews } from '@/components/Reviews';
import { Stars } from '@/components/Stars';
import { ErrorMsg } from '@/components/StatusMessage';

const BASE_PERKS = [
  'وصول كامل لكل دروس الدورة',
  'اختبارات وواجبات مع تصحيح',
  'شهادة إتمام موثّقة برمز QR',
  'مجتمع نقاش بإشراف المدرّب',
];

// ---- E1: رمز إتمام المتطلّب ----
function CheckIcon() {
  return (
    <svg
      className="inline-block shrink-0 text-emerald-600"
      width="15"
      height="15"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden
    >
      <path
        d="m5 13 4 4 10-10"
        stroke="currentColor"
        strokeWidth="2.4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function CourseDetailClient({ course }: { course: Course }) {
  const { user } = useAuth();
  const router = useRouter();
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  // ---- E1: قائمة الالتحاقات المكتملة للمتعلّم (Set<courseId>) ----
  // تُجلب مرة واحدة عند تحميل الجزيرة لمستخدم مصادَق فقط.
  const [completedIds, setCompletedIds] = useState<Set<number>>(new Set());
  const [enrollsLoaded, setEnrollsLoaded] = useState(false);

  useEffect(() => {
    // لا جلب إن لم يكن المستخدم مسجّلاً
    if (!user) { setEnrollsLoaded(true); return; }
    api<{ data: Enrollment[] }>('/enrollments')
      .then((res) => {
        const ids = new Set(
          res.data
            .filter((e) => e.status === 'completed')
            .map((e) => e.course_id),
        );
        setCompletedIds(ids);
      })
      .catch(() => {
        // إن فشل الجلب، يبقى الزرّ مفعّلاً — الخادم يحمي عند الإرسال (§4.أ)
      })
      .finally(() => setEnrollsLoaded(true));
  }, [user]);

  // ---- E1: حساب المتطلّبات الناقصة ----
  const prerequisites: CoursePrerequisite[] = course.prerequisites ?? [];
  // المتطلّبات الناقصة — تُحسب فقط بعد تحميل الالتحاقات
  const missingPrereqs: CoursePrerequisite[] = enrollsLoaded && user
    ? prerequisites.filter((p) => !completedIds.has(p.id))
    : [];

  // الزرّ معطّل إن وُجدت متطلّبات ناقصة (ومستخدم مصادَق وبيانات محمّلة)
  const isBlocked = enrollsLoaded && user !== null && missingPrereqs.length > 0;

  // ---- E1: معالجة جسم 422 من الالتحاق ----
  // يُستخرج من err.body?.prerequisites قائمة المقررات الناقصة (§1.د)
  const [prereqErrors, setPrereqErrors] = useState<CoursePrerequisite[]>([]);

  async function enroll() {
    if (!user) { router.push('/login'); return; }
    if (isBlocked) return; // حماية مزدوجة — الزرّ مغلق أصلاً
    setBusy(true); setMsg(''); setPrereqErrors([]);
    try {
      await api(`/catalog/courses/${course.slug}/enroll`, { method: 'POST' });
      router.push(`/learn/${course.slug}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setMsg(err.message || t('common.error'));
        // E1: 422 مع حقل prerequisites في الجسم → عرض روابط المقررات الناقصة
        const body = err.body as { prerequisites?: CoursePrerequisite[] } | undefined;
        if (err.status === 422 && Array.isArray(body?.prerequisites)) {
          setPrereqErrors(body.prerequisites);
        }
      } else {
        const detail = err instanceof Error ? err.message : String(err);
        setMsg(detail || t('common.error'));
        console.error('enroll failed:', err);
      }
    } finally { setBusy(false); }
  }

  const sectionsCount = course.sections?.length ?? 0;
  const lessonsCount = course.sections?.reduce((n, s) => n + s.lessons.length, 0) ?? 0;
  const perks = (course.passing_grade ?? 0) > 0
    ? [...BASE_PERKS, `اجتياز التقييمات بدرجة ${course.passing_grade}% للحصول على الشهادة`]
    : BASE_PERKS;

  return (
    <section>
      {/* G3: text-slate-400 → text-slate-500 على روابط breadcrumb (خلفية بيضاء) */}
      <nav className="mb-4 flex flex-wrap items-center gap-1.5 text-xs text-slate-500" aria-label="مسار التنقّل">
        <Link className="text-slate-500 hover:text-brand-600" href="/">الرئيسية</Link>
        <span aria-hidden>‹</span>
        <Link className="text-slate-500 hover:text-brand-600" href="/catalog">{t('nav.catalog')}</Link>
        <span aria-hidden>‹</span>
        <span className="font-medium text-slate-500">{course.title}</span>
      </nav>

      {/* Hero */}
      <div className="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-900 via-brand-700 to-brand-500 p-8 text-white shadow-card sm:p-10">
        <div className="pointer-events-none absolute -bottom-24 -start-10 h-64 w-64 rounded-full bg-white/10 blur-3xl" aria-hidden />
        <div className="relative max-w-2xl">
          <div className="flex flex-wrap items-center gap-2">
            {course.category?.name && <span className="badge bg-white/15 text-white">{course.category.name}</span>}
            <span className="badge bg-white/15 text-white">
              {course.pricing_type === 'free' ? t('course.free') : formatMinor(course.price_minor)}
            </span>
          </div>
          <h1 className="mt-4 text-3xl font-extrabold leading-snug text-white sm:text-4xl">{course.title}</h1>
          <p className="mt-3 leading-8 text-brand-50/90">{course.description ?? course.summary}</p>

          <div className="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-brand-100">
            {course.instructor?.name && (
              <span className="flex items-center gap-2">
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-xs font-extrabold">
                  {course.instructor.name.slice(0, 1)}
                </span>
                {course.instructor.name}
              </span>
            )}
            <span>{sectionsCount} أقسام</span>
            <span>{lessonsCount} درساً</span>
            {(course.reviews_count ?? 0) > 0 && course.rating != null && (
              <span className="flex items-center gap-1.5">
                <Stars value={course.rating} size={14} />
                <span>{course.rating} ({course.reviews_count})</span>
              </span>
            )}
            <span className="flex items-center gap-1">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden>
                <path d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-3 1.5L8 21l4-2 4 2-1-5.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
              شهادة إتمام
            </span>
          </div>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        {/* Curriculum + E1: قسم المتطلّبات السابقة */}
        <div className="md:col-span-2">

          {/* ---- E1: قسم المتطلّبات السابقة (يظهر إن وُجدت متطلّبات) ---- */}
          {prerequisites.length > 0 && (
            <div className="card mb-6" aria-label={t('prereq.title')}>
              <h2 className="mb-3 text-lg font-bold text-slate-900">{t('prereq.title')}</h2>
              <ul className="space-y-2">
                {prerequisites.map((p) => {
                  const done = completedIds.has(p.id);
                  return (
                    <li key={p.id} className="flex items-center gap-3 text-sm">
                      {/* شارة الحالة: مكتمل أو مطلوب */}
                      {done ? (
                        <span
                          className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"
                          aria-label={t('prereq.completed')}
                        >
                          <CheckIcon />
                          {t('prereq.completed')}
                        </span>
                      ) : (
                        <span
                          className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700"
                          aria-label={t('prereq.required')}
                        >
                          {t('prereq.required')}
                        </span>
                      )}
                      <Link
                        href={`/catalog/${p.slug}`}
                        className="text-brand-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600"
                      >
                        {p.title}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          )}

          <h2 className="mb-4 text-2xl font-extrabold">محتوى الدورة</h2>
          {course.sections?.length ? course.sections.map((s, si) => (
            <div key={s.id} className="card p-0">
              <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                <strong className="text-slate-900">القسم {si + 1}: {s.title}</strong>
                <span className="text-xs text-slate-500">{s.lessons.length} دروس</span>
              </div>
              <ul className="divide-y divide-slate-50">
                {s.lessons.map((l) => (
                  <li key={l.id} className="flex items-center gap-3 px-5 py-3 text-sm text-slate-600">
                    <span className="text-slate-500"><LessonTypeIcon type={l.type} /></span>
                    <span className="flex-1">{l.title}</span>
                    {l.is_free_preview && <span className="badge">معاينة مجانية</span>}
                  </li>
                ))}
              </ul>
            </div>
          )) : (
            <div className="card text-slate-500">سيُنشر المنهج التفصيلي قريباً.</div>
          )}

          <div className="mt-6">
            <Reviews courseSlug={course.slug} />
          </div>
        </div>

        {/* Sticky enrollment card */}
        <aside>
          <div className="card sticky top-20">
            <div className="mb-4 text-center">
              <div className="text-3xl font-extrabold text-brand-700">
                {course.pricing_type === 'free' ? t('course.free') : formatMinor(course.price_minor)}
              </div>
              {course.pricing_type !== 'free' && <p className="mt-1 text-xs text-slate-500">دفعة واحدة — وصول دائم</p>}
            </div>

            {/* G5: role="alert" عبر ErrorMsg */}
            <ErrorMsg msg={msg} />

            {/* ---- E1: روابط المقررات الناقصة عند 422 من الخادم ---- */}
            {prereqErrors.length > 0 && (
              <div className="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800" role="alert" aria-live="assertive">
                <p className="mb-2 font-semibold">يجب إكمال المتطلّبات التالية أولاً:</p>
                <ul className="space-y-1">
                  {prereqErrors.map((p) => (
                    <li key={p.id}>
                      <Link
                        href={`/catalog/${p.slug}`}
                        className="underline hover:text-amber-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-700"
                      >
                        {p.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* ---- E1: رسالة ومتطلّبات ناقصة (حساب محلي) ---- */}
            {isBlocked && (
              <div className="mb-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                <p className="mb-2 font-semibold">المتطلّبات الناقصة للالتحاق:</p>
                <ul className="space-y-1">
                  {missingPrereqs.map((p) => (
                    <li key={p.id}>
                      <Link
                        href={`/catalog/${p.slug}`}
                        className="text-brand-700 underline hover:text-brand-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600"
                      >
                        {p.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div className="page-actions flex-col">
              {/*
                E1: الزرّ المعطّل عند وجود متطلّبات ناقصة.
                — disabled: يمنع التفعيل فعلياً
                — aria-disabled="true": يُعلم قارئ الشاشة
                — نصّ سبب واضح مرئي «أكمل المتطلّبات أولاً» (لا اعتماد على اللون)
                — opacity-50 + cursor-not-allowed: تباين AA مرئي
              */}
              <button
                className={`btn w-full ${isBlocked ? 'cursor-not-allowed opacity-50' : ''}`}
                onClick={() => void enroll()}
                disabled={busy || isBlocked}
                aria-disabled={isBlocked ? 'true' : undefined}
              >
                {busy
                  ? t('common.loading')
                  : isBlocked
                    ? t('prereq.blockedCta')
                    : t('course.enroll')}
              </button>
              {course.pricing_type !== 'free' && (
                <Link className="btn btn-ghost w-full" href={`/checkout/${course.slug}`}>{t('course.buy')}</Link>
              )}
              <Link className="btn btn-ghost w-full" href={`/community/${course.slug}`}>{t('community.title')}</Link>
            </div>

            <ul className="mt-5 space-y-2.5 border-t border-slate-100 pt-4 text-sm text-slate-600">
              {perks.map((perk) => (
                <li key={perk} className="flex items-center gap-2">
                  <svg className="shrink-0 text-emerald-500" width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="m5 13 4 4 10-10" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                  {perk}
                </li>
              ))}
            </ul>
          </div>
        </aside>
      </div>
    </section>
  );
}
