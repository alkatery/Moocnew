'use client';

import Link from 'next/link';
import { useSiteContent } from '@/lib/siteContent';
import { t } from '@/i18n/dictionary';
import { ContactForm } from '@/components/ContactForm';

const FAQS = [
  { q: 'هل الشهادات موثّقة؟', a: 'نعم — كل شهادة تحمل رمز QR يقود لصفحة تحقق علنية تؤكد صحتها فوراً.' },
  { q: 'هل توجد دورات مجانية؟', a: 'نعم، المكتبة المفتوحة تضم دورات مجانية كاملة، إضافة لدروس معاينة في الدورات المدفوعة.' },
  { q: 'أنا مدرّب — كيف أنشر دورتي؟', a: 'أنشئ حساباً ثم راسلنا من هذا النموذج لتفعيل صلاحية المدرّب، وستجد استوديو متكاملاً لبناء دورتك.' },
];

export default function ContactPage() {
  const { c } = useSiteContent();
  const email = c('brand.support_email', 'support@mooc.example');

  return (
    <>
      <section className="mb-8 text-center">
        <h1 className="text-3xl font-extrabold">{t('contact.title')}</h1>
        <p className="mx-auto mt-2 max-w-xl text-slate-500">
          {c('contact.intro', 'سؤال، اقتراح، أو مشكلة تقنية؟ راسلنا وسنرد عليك خلال يوم عمل واحد.')}
        </p>
      </section>

      <div className="grid gap-8 md:grid-cols-5">
        <div className="md:col-span-3">
          <ContactForm />
        </div>

        <aside className="md:col-span-2">
          <div className="card">
            <strong className="text-slate-900">قنوات أخرى</strong>
            <ul className="mt-3 space-y-3 text-sm text-slate-600">
              <li className="flex items-center gap-2">
                <span className="icon-tile h-9 w-9">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="M4 6h16v12H4z" stroke="currentColor" strokeWidth="1.7" />
                    <path d="m4 7 8 6 8-6" stroke="currentColor" strokeWidth="1.7" />
                  </svg>
                </span>
                <a href={`mailto:${email}`}>{email}</a>
              </li>
              <li className="flex items-center gap-2">
                <span className="icon-tile h-9 w-9">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="M4 5h16v10H9l-5 4z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" />
                  </svg>
                </span>
                <span>
                  الطلاب الملتحقون: افتح تذكرة دعم من صفحة
                  {' '}<Link href="/learn">تعلّمي</Link>
                </span>
              </li>
            </ul>
          </div>

          <div className="card">
            <strong className="text-slate-900">أسئلة شائعة</strong>
            <div className="mt-3 space-y-4">
              {FAQS.map((f) => (
                <div key={f.q}>
                  <p className="text-sm font-bold text-slate-800">{f.q}</p>
                  <p className="mt-1 text-sm leading-6 text-slate-500">{f.a}</p>
                </div>
              ))}
            </div>
          </div>
        </aside>
      </div>
    </>
  );
}
