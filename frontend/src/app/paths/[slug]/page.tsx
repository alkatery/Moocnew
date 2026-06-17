// غلاف خادمي (Server Component) — ينفّذ generateMetadata ويحقن JSON-LD ويُصيَّر عند الطلب.
// العقد: B2-content-seo.md §3

import { cache } from 'react';
import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import { API_BASE } from '@/lib/api';
import type { PathDetail } from '@/lib/types';
import { PathDetailClient } from './PathDetailClient';

// الصفحة ديناميكية — تُصيَّر عند كل طلب (§2).
export const dynamic = 'force-dynamic';

// ثابت الموقع — لا metadataBase، كل الروابط مطلقة (§2).
const SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000';

// ---------------------------------------------------------------------------
// دالة مساعدة: تحويل مسار الصورة إلى رابط مطلق (§2).
// ---------------------------------------------------------------------------
function absImage(path: string): string {
  if (path.startsWith('http')) return path;
  return `${SITE}${path.startsWith('/') ? '' : '/'}${path}`;
}

// ---------------------------------------------------------------------------
// دالة مساعدة: اقتطاع النص عند حدود الكلمة (§2).
// ---------------------------------------------------------------------------
function truncate(text: string, n: number): string {
  if (text.length <= n) return text;
  const cut = text.lastIndexOf(' ', n);
  return (cut > 0 ? text.slice(0, cut) : text.slice(0, n)) + '…';
}

// ---------------------------------------------------------------------------
// جلب المسار خادمياً — بلا Authorization (النقطة عامّة §1.أ).
// مغلَّف بـ react.cache لتفادي ازدواج الطلب بين generateMetadata والصفحة.
// يعيد PathDetail|null ولا يرمي (أي خطأ → null).
// ---------------------------------------------------------------------------
const getPath = cache(async (slug: string): Promise<PathDetail | null> => {
  try {
    const res = await fetch(`${API_BASE}/learning/paths/${slug}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store', // يضمن التصيير الديناميكي عند الطلب
    });
    if (!res.ok) return null;
    const json = await res.json() as { data: PathDetail };
    return json.data ?? null;
  } catch {
    return null;
  }
});

// ---------------------------------------------------------------------------
// بناء JSON-LD وفق §3.4:
//   EducationalOccupationalProgram + ItemList (تسطيح levels تصاعدياً) + BreadcrumbList
// لا Offer على مستوى البرنامج — التسعير على مستوى كل دورة (§3.4).
// ---------------------------------------------------------------------------
function pathJsonLd(path: PathDetail, site: string): object {
  // تسطيح المستويات تصاعدياً ثم item.position للحصول على ترتيب مسار التعلّم الفعلي
  const allItems = path.levels
    .slice()
    .sort((a, b) => a.level - b.level)
    .flatMap((level) =>
      level.items.slice().sort((a, b) => a.position - b.position),
    );

  const totalCourses = allItems.length;

  // عُقدة البرنامج التعليمي
  const programNode: Record<string, unknown> = {
    '@type': 'EducationalOccupationalProgram',
    'name': path.title,
    'description': truncate(path.description ?? path.summary ?? path.title, 5000),
    'url': `${site}/paths/${path.slug}`,
    'inLanguage': 'ar',
    'provider': {
      '@type': 'Organization',
      'name': 'منصة MOOC',
      'url': site,
    },
    'educationalProgramMode': 'online',
  };

  // image — يُحذف إن لا غلاف (§3.4 — قواعد الحذف الشرطي)
  if (path.cover_image) {
    programNode['image'] = absImage(path.cover_image);
  }

  // numberOfCredits — يُحذف إن 0 (§3.4)
  if (totalCourses > 0) {
    programNode['numberOfCredits'] = totalCourses;
  }

  // hasCourse — يُحذف كاملاً إن لا دورات (§3.4)
  if (totalCourses > 0) {
    programNode['hasCourse'] = allItems.map((item) => ({
      '@type': 'Course',
      'name': item.course.title,
      'url': `${site}/catalog/${item.course.slug}`,
      'provider': {
        '@type': 'Organization',
        'name': 'منصة MOOC',
        'url': site,
      },
      'hasCourseInstance': {
        '@type': 'CourseInstance',
        'courseMode': 'online',
        'inLanguage': 'ar',
      },
    }));
  }

  // بناء الـ graph — ItemList يُحذف إن لا دورات (§3.4)
  const graph: unknown[] = [programNode];

  if (totalCourses > 0) {
    graph.push({
      '@type': 'ItemList',
      'itemListOrder': 'https://schema.org/ItemListOrderAscending',
      'numberOfItems': totalCourses,
      'itemListElement': allItems.map((item, idx) => ({
        '@type': 'ListItem',
        'position': idx + 1,
        'url': `${site}/catalog/${item.course.slug}`,
        'name': item.course.title,
      })),
    });
  }

  graph.push({
    '@type': 'BreadcrumbList',
    'itemListElement': [
      { '@type': 'ListItem', 'position': 1, 'name': 'الرئيسية', 'item': site },
      { '@type': 'ListItem', 'position': 2, 'name': 'المسارات',  'item': `${site}/paths` },
      { '@type': 'ListItem', 'position': 3, 'name': path.title,   'item': `${site}/paths/${path.slug}` },
    ],
  });

  return {
    '@context': 'https://schema.org',
    '@graph': graph,
  };
}

// ---------------------------------------------------------------------------
// generateMetadata — params غير متزامن في Next.js 15 (§3.3).
// ---------------------------------------------------------------------------
export async function generateMetadata(
  { params }: { params: Promise<{ slug: string }> },
): Promise<Metadata> {
  const { slug } = await params;
  const path = await getPath(slug);

  // حالة الغياب — ميتاداتا آمنة بلا canonical (§3.3)
  if (!path) {
    return {
      title: 'المسار غير متوفّر — منصة MOOC',
      description: 'لم نعثر على هذا المسار.',
    };
  }

  const title = `${path.title} — منصة MOOC`;
  const description = truncate(path.description ?? path.summary ?? '', 160);
  const url = `${SITE}/paths/${path.slug}`;

  // قائمة الصور — تُحذف إن لا cover_image (§3.3)
  const images = path.cover_image ? [absImage(path.cover_image)] : undefined;

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
      type: 'website',
      locale: 'ar_SA',
      siteName: 'منصة MOOC',
      ...(images ? { images } : {}),
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
      ...(images ? { images } : {}),
    },
  };
}

// ---------------------------------------------------------------------------
// الغلاف الخادمي الرئيسي — Server Component (§3.1).
// ---------------------------------------------------------------------------
export default async function PathDetailPage(
  { params }: { params: Promise<{ slug: string }> },
) {
  const { slug } = await params;
  const path = await getPath(slug);

  // مسار غير موجود → 404 (§3.3)
  if (!path) notFound();

  return (
    <>
      {/* حقن JSON-LD وفق §3.4 */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(pathJsonLd(path, SITE)) }}
      />
      {/* تمرير المسار كاملاً إلى الجزيرة التفاعلية — viewer بقيم الزائر (مقصود §3.1) */}
      <PathDetailClient path={path} />
    </>
  );
}
