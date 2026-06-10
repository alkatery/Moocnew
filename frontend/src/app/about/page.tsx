'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { PlatformStats } from '@/lib/types';
import { formatCount } from '@/lib/format';
import { t } from '@/i18n/dictionary';

const VALUES = [
  { title: 'جودة لا تساوم', body: 'كل دورة تمر بمراجعة تحريرية وفنية قبل النشر، ولا يُعتمد إلا المحتوى الذي يستحق وقتك.' },
  { title: 'بالعربية أولاً', body: 'واجهة وتجربة ومحتوى صُمّمت من اليوم الأول بالعربية وباتجاه RTL — لا ترجمة متأخرة.' },
  { title: 'إثبات حقيقي للتعلّم', body: 'اختبارات وواجبات مصحّحة وشهادات برمز QR يمكن لأي جهة توظيف التحقق منها فوراً.' },
];

const STEPS = [
  { n: '١', title: 'أنشئ حسابك', body: 'التسجيل مجاني ويستغرق أقل من دقيقة.' },
  { n: '٢', title: 'التحق بدورة', body: 'ابدأ بالمكتبة المجانية أو اختر دورة مدفوعة.' },
  { n: '٣', title: 'تعلّم وأنجز', body: 'تابع الدروس، حلّ الاختبارات، واحضر الجلسات المباشرة.' },
  { n: '٤', title: 'احصل على شهادتك', body: 'شهادة إتمام موثّقة تنزّلها وتشاركها فوراً.' },
];

export default function AboutPage() {
  const [stats, setStats] = useState<PlatformStats | null>(null);

  useEffect(() => {
    void api<{ data: PlatformStats }>('/platform/stats', { auth: false })
      .then((r) => setStats(r.data)).catch(() => undefined);
  }, []);

  return (
    <>
      <section className="overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-900 via-brand-700 to-brand-500 px-8 py-14 text-white shadow-card">
        <span className="badge bg-white/15 text-white">{t('nav.about')}</span>
        <h1 className="mt-4 max-w-2xl text-4xl font-extrabold leading-tight text-white">
          نفتح أبواب المعرفة لكل متحدث بالعربية
        </h1>
        <p className="mt-4 max-w-2xl text-lg leading-8 text-brand-50/90">
          منصة تعليم جماهيري مفتوح (MOOC) تجمع نخبة المدرّبين مع متعلّمين طموحين:
          دورات فيديو تفاعلية، تقييمات حقيقية، جلسات مباشرة، وشهادات موثّقة —
          في تجربة واحدة متكاملة.
        </p>
      </section>

      {stats && (
        <section className="-mt-8 px-2 sm:px-6" aria-label="أرقام المنصة">
          <div className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-card sm:grid-cols-4">
            {[
              { label: 'دورة منشورة', value: stats.courses },
              { label: 'متعلّم ومتعلّمة', value: stats.learners },
              { label: 'مدرّب خبير', value: stats.instructors },
              { label: 'التحاق بالدورات', value: stats.enrollments },
            ].map((s) => (
              <div key={s.label} className="text-center">
                <div className="text-3xl font-extrabold text-brand-700">{formatCount(s.value)}</div>
                <div className="mt-1 text-sm text-slate-500">{s.label}</div>
              </div>
            ))}
          </div>
        </section>
      )}

      <section className="section">
        <div className="section-head"><h2>قيمنا</h2></div>
        <div className="grid gap-4 md:grid-cols-3">
          {VALUES.map((v) => (
            <div key={v.title} className="card mb-0">
              <strong className="text-slate-900">{v.title}</strong>
              <p className="mt-2 text-sm leading-7 text-slate-500">{v.body}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="section">
        <div className="section-head"><h2>كيف تعمل المنصة؟</h2></div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {STEPS.map((s) => (
            <div key={s.n} className="card mb-0 text-center">
              <span className="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-brand-600 text-lg font-extrabold text-white">{s.n}</span>
              <strong className="mt-3 block text-slate-900">{s.title}</strong>
              <p className="mt-1 text-sm text-slate-500">{s.body}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="section overflow-hidden rounded-3xl bg-brand-50 p-10 text-center">
        <h2 className="text-2xl font-extrabold">جاهز تبدأ؟</h2>
        <p className="mx-auto mt-2 max-w-lg text-slate-600">انضم إلى آلاف المتعلّمين، أو قدّم دورتك الأولى كمدرّب.</p>
        <div className="mt-5 flex justify-center gap-3">
          <Link className="btn" href="/register">{t('nav.register')}</Link>
          <Link className="btn btn-ghost" href="/contact">{t('nav.contact')}</Link>
        </div>
      </section>
    </>
  );
}
