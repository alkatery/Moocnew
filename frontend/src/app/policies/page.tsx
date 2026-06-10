'use client';

import { useSiteContent } from '@/lib/siteContent';

/**
 * Public published policies (NELC compliance): attendance tracking and
 * academic integrity. Content is editable from the admin site-content
 * panel and served by GET /api/v1/content/site.
 */
export default function PoliciesPage() {
  const { c } = useSiteContent();

  const policies = [
    {
      title: c('policy.attendance.title', 'سياسة الحضور والمواظبة'),
      body: c(
        'policy.attendance.body',
        'يُحتسب الحضور في المنصة عبر التقدم الفعلي في محتوى الدورة: يُسجَّل إكمال كل درس تلقائياً في سجل تقدم المتعلم، وتُعد الدورة منجزة عند إكمال جميع دروسها واجتياز تقييماتها.',
      ),
    },
    {
      title: c('policy.integrity.title', 'سياسة النزاهة الأكاديمية'),
      body: c(
        'policy.integrity.body',
        'تلتزم المنصة بمعايير النزاهة الأكاديمية: التحقق من هوية المتعلم، ومنع الغش في الاختبارات والواجبات بكل صوره، وتوثيق المخالفات وتطبيق عواقبها المتدرجة.',
      ),
    },
  ];

  return (
    <>
      <section className="overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-900 via-brand-700 to-brand-500 px-8 py-12 text-white shadow-card">
        <span className="badge bg-white/15 text-white">السياسات</span>
        <h1 className="mt-4 max-w-2xl text-4xl font-extrabold leading-tight text-white">سياسات المنصة</h1>
        <p className="mt-4 max-w-2xl text-lg leading-8 text-brand-50/90">
          السياسات المنشورة وفق متطلبات المركز الوطني للتعليم الإلكتروني: كيف نحتسب الحضور، وكيف نحمي نزاهة التعلّم.
        </p>
      </section>

      <section className="section">
        <div className="grid gap-4">
          {policies.map((p) => (
            <article key={p.title} className="card mb-0">
              <h2 className="text-xl font-extrabold text-slate-900">{p.title}</h2>
              <p className="mt-3 whitespace-pre-line text-sm leading-8 text-slate-600">{p.body}</p>
            </article>
          ))}
        </div>
      </section>
    </>
  );
}
