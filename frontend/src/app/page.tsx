'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import type { Category, Course, NewsPost, Paginated, PlatformStats } from '@/lib/types';
import { formatCount } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { CourseCard } from '@/components/CourseCard';
import { NewsCard } from '@/components/NewsCard';
import { ContactForm } from '@/components/ContactForm';

const FEATURES = [
  {
    title: 'دروس فيديو احترافية',
    body: 'بث محمي وسلس من مزوّدين موثوقين، مع دعم يوتيوب والمعاينات المجانية.',
    icon: <path d="M4 5h16v12H4zM10 9l5 2.5L10 14zM8 20h8" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" />,
  },
  {
    title: 'اختبارات وواجبات',
    body: 'بنك أسئلة، محاولات مؤقّتة، وتصحيح وتغذية راجعة من المدرّب.',
    icon: <path d="M9 5h6m-7 4h8m-8 4h5M6 3h12v18H6zM15 16l2 2 3-3" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  },
  {
    title: 'شهادات موثّقة',
    body: 'شهادة إتمام لكل دورة برمز QR قابل للتحقق العلني في أي وقت.',
    icon: <path d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-3 1.5L8 21l4-2 4 2-1-5.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  },
  {
    title: 'جلسات مباشرة',
    body: 'محاضرات عبر زوم أو قوقل ميت مع تقويم ميلادي/هجري وتذكيرات تلقائية.',
    icon: <path d="M4 6h12v12H4zM16 10l4-2v8l-4-2M7 3v3m6-3v3" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  },
  {
    title: 'مجتمع نقاش لكل دورة',
    body: 'اسأل وشارك وتعلّم مع زملائك تحت إشراف فريق المنصة.',
    icon: <path d="M4 5h16v10H9l-5 4zM8 9h8m-8 3h5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  },
  {
    title: 'إشعارات ذكية',
    body: 'تنبيهات بالبريد وSMS وواتساب حسب تفضيلاتك أنت — لا إزعاج.',
    icon: <path d="M6 16v-5a6 6 0 1 1 12 0v5l2 3H4zM10 21h4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  },
];

const TESTIMONIALS = [
  { name: 'أروى الشمري', role: 'متعلّمة — تطوير ويب', quote: 'أنهيت ثلاث دورات وحصلت على شهادات موثّقة ساعدتني فعلاً في التقديم على وظيفتي الأولى.' },
  { name: 'محمد العمري', role: 'مدرّب معتمد', quote: 'استوديو المدرّس سهّل عليّ رفع الدروس وبناء الاختبارات ومتابعة تقدّم الطلاب من مكان واحد.' },
  { name: 'هند القحطاني', role: 'متعلّمة — إدارة أعمال', quote: 'الجلسات المباشرة والتذكيرات على واتساب خلّتني ما أفوّت أي محاضرة. تجربة متكاملة بالعربية.' },
];

const CATEGORY_ICONS = [
  <path key="book" d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 0-3 3V4Zm0 13h14M9 8h6" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  <path key="code" d="m8 8-4 4 4 4m8-8 4 4-4 4m-3-10-2 12" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  <path key="chart" d="M4 20V4m0 16h16M8 16v-5m4 5V8m4 8v-3" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  <path key="globe" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-9-9h18M12 3c2.5 2.5 3.5 5.5 3.5 9S14.5 18.5 12 21c-2.5-2.5-3.5-5.5-3.5-9S9.5 5.5 12 3Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" />,
  <path key="beaker" d="M9 3h6M10 3v5l-5 9a3 3 0 0 0 2.6 4.5h8.8A3 3 0 0 0 19 17l-5-9V3M8 14h8" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
  <path key="case" d="M4 8h16v11H4zM9 8V5h6v3m-7 5h8" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />,
];

export default function HomePage() {
  const router = useRouter();
  const [q, setQ] = useState('');
  const [stats, setStats] = useState<PlatformStats | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [news, setNews] = useState<NewsPost[]>([]);

  useEffect(() => {
    void api<{ data: PlatformStats }>('/platform/stats', { auth: false })
      .then((r) => setStats(r.data)).catch(() => undefined);
    void api<Paginated<Category>>('/catalog/categories', { auth: false })
      .then((r) => setCategories(r.data.slice(0, 6))).catch(() => undefined);
    void api<Paginated<Course>>('/catalog/courses?per_page=6', { auth: false })
      .then((r) => setCourses(r.data)).catch(() => undefined);
    void api<Paginated<NewsPost>>('/content/news?per_page=3', { auth: false })
      .then((r) => setNews(r.data)).catch(() => undefined);
  }, []);

  function search(e: React.FormEvent) {
    e.preventDefault();
    router.push(q.trim() ? `/catalog?q=${encodeURIComponent(q.trim())}` : '/catalog');
  }

  const statItems = [
    { label: 'دورة منشورة', value: stats?.courses ?? 0 },
    { label: 'متعلّم ومتعلّمة', value: stats?.learners ?? 0 },
    { label: 'مدرّب خبير', value: stats?.instructors ?? 0 },
    { label: 'التحاق بالدورات', value: stats?.enrollments ?? 0 },
  ];

  return (
    <>
      {/* Hero */}
      <section className="relative overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-900 via-brand-700 to-brand-500 px-6 py-16 text-white shadow-card sm:px-12">
        <div className="pointer-events-none absolute -start-20 -top-24 h-72 w-72 rounded-full bg-white/10 blur-2xl" aria-hidden />
        <div className="pointer-events-none absolute -bottom-32 -end-16 h-80 w-80 rounded-full bg-brand-400/30 blur-3xl" aria-hidden />
        <svg className="pointer-events-none absolute end-10 top-10 hidden opacity-20 lg:block" width="220" height="220" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="M12 3 2 8l10 5 8-4v6h2V8L12 3Z" fill="#fff" />
          <path d="M6 12.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-3.5l-6 3-6-3Z" fill="#fff" />
        </svg>

        <div className="relative max-w-2xl">
          <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-xs font-semibold backdrop-blur">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-300" aria-hidden />
            منصة تعليم عربية مفتوحة — تعلّم في أي وقت ومن أي مكان
          </span>
          <h1 className="mt-5 text-4xl font-extrabold leading-tight text-white sm:text-5xl">
            تعلّم مهارات المستقبل
            <span className="block text-brand-100">بالعربية… وبشهادات موثّقة</span>
          </h1>
          <p className="mt-4 max-w-xl text-lg leading-8 text-brand-50/90">
            دورات فيديو تفاعلية مع اختبارات وواجبات وجلسات مباشرة ومجتمع نقاش،
            تنتهي بشهادة إتمام برمز QR قابل للتحقق.
          </p>

          <form className="mt-7 flex max-w-xl gap-2" onSubmit={search} role="search">
            <input
              className="input m-0 flex-1 border-0 bg-white/95 py-3 text-slate-900"
              placeholder={t('home.searchPlaceholder')}
              value={q}
              onChange={(e) => setQ(e.target.value)}
              aria-label={t('home.searchPlaceholder')}
            />
            <button className="btn bg-white px-6 text-brand-700 hover:bg-brand-50" type="submit">
              {t('home.searchCta')}
            </button>
          </form>

          <div className="mt-5 flex flex-wrap items-center gap-2 text-xs text-brand-100">
            <span>الأكثر طلباً:</span>
            {(categories.length ? categories.slice(0, 3).map((c) => ({ label: c.name, href: `/catalog?category=${c.slug}` })) : [
              { label: 'كل الدورات', href: '/catalog' },
              { label: 'المجانية', href: '/catalog?pricing=free' },
            ]).map((s) => (
              <Link key={s.label} href={s.href}
                className="rounded-full bg-white/10 px-3 py-1 font-semibold text-white transition hover:bg-white/20">
                {s.label}
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Stats */}
      <section className="-mt-8 px-2 sm:px-6" aria-label="أرقام المنصة">
        <div className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-card sm:grid-cols-4">
          {statItems.map((s) => (
            <div key={s.label} className="text-center">
              <div className="text-3xl font-extrabold text-brand-700">{formatCount(s.value)}</div>
              <div className="mt-1 text-sm text-slate-500">{s.label}</div>
            </div>
          ))}
        </div>
      </section>

      {/* Categories */}
      {categories.length > 0 && (
        <section className="section">
          <div className="section-head">
            <h2>تصفّح حسب المجال</h2>
            <Link className="text-sm font-semibold" href="/catalog">{t('common.viewAll')}</Link>
          </div>
          <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            {categories.map((c, i) => (
              <Link key={c.id} href={`/catalog?category=${c.slug}`}
                className="card mb-0 flex flex-col items-center gap-2 py-6 text-center transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg">
                <span className="icon-tile">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>{CATEGORY_ICONS[i % CATEGORY_ICONS.length]}</svg>
                </span>
                <strong className="text-sm text-slate-800">{c.name}</strong>
              </Link>
            ))}
          </div>
        </section>
      )}

      {/* Featured courses */}
      <section className="section">
        <div className="section-head">
          <h2>دورات مختارة لك</h2>
          <Link className="text-sm font-semibold" href="/catalog">{t('common.viewAll')}</Link>
        </div>
        {courses.length === 0 ? (
          <p className="label">{t('catalog.empty')}</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {courses.map((c, i) => <CourseCard key={c.id} course={c} index={i} />)}
          </div>
        )}
      </section>

      {/* Why us */}
      <section className="section">
        <div className="section-head">
          <h2>لماذا تتعلّم معنا؟</h2>
          <p>كل ما تحتاجه لرحلة تعلّم مكتملة — من الدرس الأول حتى الشهادة.</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map((f) => (
            <div key={f.title} className="card mb-0 flex gap-4">
              <span className="icon-tile">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>{f.icon}</svg>
              </span>
              <div>
                <strong className="text-slate-900">{f.title}</strong>
                <p className="mt-1 text-sm leading-6 text-slate-500">{f.body}</p>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* Open library */}
      <section className="section overflow-hidden rounded-3xl bg-brand-50 p-8 sm:p-10">
        <div className="grid items-center gap-8 md:grid-cols-2">
          <div>
            <span className="badge">المكتبة المفتوحة</span>
            <h2 className="mt-3 text-2xl font-extrabold">ابدأ مجاناً اليوم — دون أي التزام</h2>
            <p className="mt-3 leading-7 text-slate-600">
              مكتبة كاملة من الدورات المجانية بالفيديو والاختبارات، تشمل دروس
              معاينة مفتوحة في الدورات المدفوعة. سجّل والتحق خلال دقيقة.
            </p>
            <div className="page-actions mt-5">
              <Link className="btn" href="/catalog?pricing=free">تصفّح المكتبة المجانية</Link>
              <Link className="btn btn-ghost" href="/register">{t('nav.register')}</Link>
            </div>
          </div>
          <ul className="space-y-3 text-sm">
            {['دورات مجانية كاملة بشهادات إتمام', 'دروس معاينة مفتوحة بلا تسجيل دفع', 'تقدّمك محفوظ ويُزامَن على كل أجهزتك'].map((line) => (
              <li key={line} className="flex items-center gap-3 rounded-xl bg-white p-4 shadow-card">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="m5 13 4 4 10-10" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                </span>
                <span className="font-medium text-slate-700">{line}</span>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* News */}
      {news.length > 0 && (
        <section className="section">
          <div className="section-head">
            <h2>{t('news.title')}</h2>
            <Link className="text-sm font-semibold" href="/news">{t('common.viewAll')}</Link>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {news.map((p) => <NewsCard key={p.id} post={p} />)}
          </div>
        </section>
      )}

      {/* Testimonials */}
      <section className="section">
        <div className="section-head"><h2>قالوا عن المنصة</h2></div>
        <div className="grid gap-4 md:grid-cols-3">
          {TESTIMONIALS.map((item) => (
            <figure key={item.name} className="card mb-0">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" className="text-brand-200" aria-hidden>
                <path d="M10 7H6a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2v2a2 2 0 0 1-2 2m14-12h-4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2v2a2 2 0 0 1-2 2" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
              <blockquote className="mt-3 text-sm leading-7 text-slate-600">{item.quote}</blockquote>
              <figcaption className="mt-4 border-t border-slate-100 pt-3">
                <strong className="block text-sm text-slate-900">{item.name}</strong>
                <span className="text-xs text-slate-400">{item.role}</span>
              </figcaption>
            </figure>
          ))}
        </div>
      </section>

      {/* About + Contact */}
      <section className="section" id="contact">
        <div className="grid gap-8 md:grid-cols-2">
          <div>
            <span className="badge">من نحن</span>
            <h2 className="mt-3 text-2xl font-extrabold">منصة عربية للتعليم المفتوح</h2>
            <p className="mt-3 leading-8 text-slate-600">
              نبني تجربة تعلّم عربية متكاملة: محتوى عالي الجودة من مدرّبين خبراء،
              تقييمات حقيقية تثبت إتقانك، وشهادات يمكن لأي جهة التحقق منها فوراً.
              هدفنا أن يكون التعلّم الجاد متاحاً للجميع.
            </p>
            <Link className="btn btn-ghost mt-4" href="/about">اعرف المزيد عنا</Link>

            <div className="mt-8 space-y-4">
              <div className="flex items-center gap-3">
                <span className="icon-tile">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="M4 6h16v12H4z" stroke="currentColor" strokeWidth="1.7" />
                    <path d="m4 7 8 6 8-6" stroke="currentColor" strokeWidth="1.7" />
                  </svg>
                </span>
                <div>
                  <strong className="block text-sm text-slate-900">البريد الإلكتروني</strong>
                  <a className="text-sm" href="mailto:support@mooc.example">support@mooc.example</a>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <span className="icon-tile">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.7" />
                    <path d="M12 7v5l3 3" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
                  </svg>
                </span>
                <div>
                  <strong className="block text-sm text-slate-900">وقت الاستجابة</strong>
                  <span className="text-sm text-slate-500">نرد على رسائلك خلال يوم عمل واحد</span>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h2 className="mb-4 text-2xl font-extrabold">{t('contact.title')}</h2>
            <ContactForm />
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="section overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-700 to-brand-500 px-8 py-12 text-center text-white shadow-card">
        <h2 className="text-3xl font-extrabold text-white">ابدأ رحلتك التعليمية اليوم</h2>
        <p className="mx-auto mt-3 max-w-xl text-brand-100">
          أنشئ حسابك مجاناً خلال دقيقة، والتحق بأول دورة من المكتبة المفتوحة.
        </p>
        <div className="mt-6 flex justify-center gap-3">
          <Link className="btn bg-white text-brand-700 hover:bg-brand-50" href="/register">{t('nav.register')}</Link>
          <Link className="btn btn-ghost border border-white/40 text-white ring-0 hover:bg-white/10" href="/catalog">{t('nav.catalog')}</Link>
        </div>
      </section>
    </>
  );
}
