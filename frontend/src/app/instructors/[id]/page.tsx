// غلاف خادمي (Server Component) — ينفّذ generateMetadata ويحقن JSON-LD ويُصيَّر عند الطلب.
// العقد: B2-content-seo.md §5
// لا جزيرة عميل — الصفحة عرض فقط (§5.1).

import { cache } from 'react';
import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import Link from 'next/link';
import { API_BASE } from '@/lib/api';
import type { InstructorProfile } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { Stars } from '@/components/Stars';

// الصفحة ديناميكية — تُصيَّر عند كل طلب (§2).
export const dynamic = 'force-dynamic';

// ثابت الموقع — لا metadataBase، كل الروابط مطلقة (§2).
const SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000';

// ---------------------------------------------------------------------------
// دالة مساعدة: اقتطاع النص عند حدود الكلمة (§2).
// تُرجع النص كما هو إن كان أقصر من n، وإلا تقصّ عند آخر مسافة قبل n وتُلحق «…».
// ---------------------------------------------------------------------------
function truncate(text: string, n: number): string {
  if (text.length <= n) return text;
  const cut = text.lastIndexOf(' ', n);
  return (cut > 0 ? text.slice(0, cut) : text.slice(0, n)) + '…';
}

// ---------------------------------------------------------------------------
// جلب بيانات المدرّب خادمياً — بلا Authorization (النقطة عامّة §1.ج).
// مغلَّف بـ react.cache لتفادي ازدواج الطلب بين generateMetadata والصفحة.
// يعيد InstructorProfile|null ولا يرمي (أي خطأ/404 → null).
// ---------------------------------------------------------------------------
const getInstructor = cache(async (id: string): Promise<InstructorProfile | null> => {
  try {
    const res = await fetch(`${API_BASE}/profiles/instructors/${id}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store', // يضمن التصيير الديناميكي عند الطلب
    });
    if (!res.ok) return null;
    const json = await res.json() as { data: InstructorProfile };
    return json.data ?? null;
  } catch {
    return null;
  }
});

// ---------------------------------------------------------------------------
// تصفية social_links وفق قاعدة sameAs (PDPL، §5.4):
// إبقاء القيم التي تبدأ بـ http:// أو https:// فقط.
// تُحذف أسماء المستخدمين الخام والبريد والهاتف وكل ما ليس URL مطلقاً.
// ---------------------------------------------------------------------------
function filterSameAs(social_links: Record<string, string>): string[] {
  return Object.values(social_links).filter(
    (v) => v.startsWith('http://') || v.startsWith('https://'),
  );
}

// ---------------------------------------------------------------------------
// بناء كائن JSON-LD وفق §5.4:
//   ProfilePage → Person + BreadcrumbList
// قواعد الحذف الشرطي:
//   - لا description إن لا bio
//   - لا sameAs إن لا روابط URL صالحة (PDPL)
//   - لا بيانات stats.learners (تجنّب تسريب بيانات أفراد — §5.4)
// ---------------------------------------------------------------------------
function instructorJsonLd(profile: InstructorProfile, id: string, site: string): object {
  // عُقدة Person داخل ProfilePage
  const personNode: Record<string, unknown> = {
    '@type': 'Person',
    'name': profile.name,
    'jobTitle': 'مدرّب',
    'url': `${site}/instructors/${id}`,
    'worksFor': {
      '@type': 'Organization',
      'name': 'منصة MOOC',
      'url': site,
    },
  };

  // description — يُحذف إن لا bio (§5.4)
  if (profile.bio) {
    personNode['description'] = truncate(profile.bio, 5000);
  }

  // sameAs — تصفية PDPL: روابط URL مطلقة فقط (§5.4)
  const sameAs = filterSameAs(profile.social_links);
  if (sameAs.length > 0) {
    personNode['sameAs'] = sameAs;
  }
  // لا sameAs إن المصفوفة فارغة بعد التصفية (§5.4)

  return {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'ProfilePage',
        'inLanguage': 'ar', // §7 — اتساق مع بقية الصفحات (ProfilePage هي WebPage/CreativeWork)
        'mainEntity': personNode,
      },
      {
        '@type': 'BreadcrumbList',
        'itemListElement': [
          { '@type': 'ListItem', 'position': 1, 'name': 'الرئيسية', 'item': site },
          { '@type': 'ListItem', 'position': 2, 'name': profile.name, 'item': `${site}/instructors/${id}` },
        ],
      },
    ],
  };
}

// ---------------------------------------------------------------------------
// generateMetadata — params غير متزامن في Next.js 15 (§5.3).
// التوقيع: ({ params }: { params: Promise<{ id: string }> })
// ---------------------------------------------------------------------------
export async function generateMetadata(
  { params }: { params: Promise<{ id: string }> },
): Promise<Metadata> {
  const { id } = await params;
  const profile = await getInstructor(id);

  // حالة الغياب — ميتاداتا آمنة بلا canonical (§5.3)
  if (!profile) {
    return {
      title: 'المدرّب غير متوفّر — منصة MOOC',
      description: 'لم نعثر على هذا المدرّب.',
    };
  }

  const title = `${profile.name} — مدرّب في منصة MOOC`;
  // description من bio أو fallback محسوب (§5.3)
  const description = truncate(
    profile.bio ?? `${profile.name} — مدرّب في منصة MOOC، ${profile.stats.courses} دورة منشورة.`,
    160,
  );
  const url = `${SITE}/instructors/${id}`;

  return {
    title,
    description,
    alternates: {
      canonical: url,
    },
    openGraph: {
      title,
      description,
      url,
      // نوع المحتوى: صفحة ملف شخصي (§5.3)
      type: 'profile',
      locale: 'ar_SA',
      siteName: 'منصة MOOC',
      // لا صورة — لا صورة شخصية في الاستجابة (§5.3)
    },
    twitter: {
      // summary (لا summary_large_image) — وفق §5.3
      card: 'summary',
      title,
      description,
    },
  };
}

// ---------------------------------------------------------------------------
// الغلاف الخادمي الرئيسي — Server Component (§5.1).
// يُصيَّر المحتوى مباشرةً بلا جزيرة عميل.
// ---------------------------------------------------------------------------
export default async function InstructorProfilePage(
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;
  const profile = await getInstructor(id);

  // مدرّب غير موجود أو غير مدرّب → 404 (§5.2)
  if (!profile) notFound();

  return (
    <>
      {/* حقن JSON-LD وفق §5.4 */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(instructorJsonLd(profile, id, SITE)) }}
      />

      <section>
        <PageHeader
          title={profile.name}
          crumbs={[{ label: t('profile.instructor') }]}
        />

        <div className="grid gap-6 md:grid-cols-3">
          <aside className="md:col-span-1">
            <div className="card">
              {/* أفاتار نصّي — لا صورة شخصية في البيانات (§5.3) */}
              <div
                aria-hidden="true"
                className="mx-auto mb-3 flex h-20 w-20 items-center justify-center rounded-full bg-brand-100 text-3xl font-extrabold text-brand-700"
              >
                {profile.name.slice(0, 1)}
              </div>

              {profile.bio && (
                <p className="text-sm leading-7 text-slate-600">{profile.bio}</p>
              )}

              <div className="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                <div>
                  <div className="font-extrabold text-brand-700">{profile.stats.courses}</div>
                  <div className="text-xs text-slate-500">{t('profile.courses')}</div>
                </div>
                <div>
                  <div className="font-extrabold text-brand-700">{profile.stats.learners}</div>
                  <div className="text-xs text-slate-500">متعلّم</div>
                </div>
                <div>
                  <div className="font-extrabold text-brand-700">
                    {profile.stats.rating ?? '—'}
                  </div>
                  <div className="text-xs text-slate-500">التقييم</div>
                </div>
              </div>
            </div>
          </aside>

          <div className="md:col-span-2">
            <h2 className="mb-4 text-xl font-extrabold">{t('profile.courses')}</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              {profile.courses.map((c) => (
                <Link
                  key={c.slug}
                  href={`/catalog/${c.slug}`}
                  className="card mb-0 transition hover:-translate-y-0.5 hover:shadow-lg"
                >
                  {c.cover_image && (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={c.cover_image}
                      alt={c.title}
                      className="mb-3 h-28 w-full rounded-xl object-cover"
                    />
                  )}
                  <strong className="block text-slate-900">{c.title}</strong>
                  {c.rating != null && (
                    <div className="mt-1">
                      <Stars value={c.rating} size={13} />
                    </div>
                  )}
                </Link>
              ))}
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
