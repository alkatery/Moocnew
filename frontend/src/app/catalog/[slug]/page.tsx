// غلاف خادمي (Server Component) — ينفّذ generateMetadata ويحقن JSON-LD ويُصيَّر عند الطلب.
// العقد: B1-course-seo.md

import { cache } from 'react';
import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import { API_BASE } from '@/lib/api';
import type { Course } from '@/lib/types';
import { CourseDetailClient } from './CourseDetailClient';

// الصفحة ديناميكية — تُصيَّر عند كل طلب (لا توليد ثابت وقت البناء).
export const dynamic = 'force-dynamic';

// ثابت الموقع — نفس المستخدم في sitemap.ts (لا metadataBase، كل روابط مطلقة).
const SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000';

// ---------------------------------------------------------------------------
// دالة مساعدة: تحويل مسار الصورة إلى رابط مطلق (§4).
// إن كانت تبدأ بـ http تُعاد كما هي، وإلا تُبنى من SITE.
// ---------------------------------------------------------------------------
function absImage(path: string): string {
  if (path.startsWith('http')) return path;
  return `${SITE}${path.startsWith('/') ? '' : '/'}${path}`;
}

// ---------------------------------------------------------------------------
// دالة مساعدة: اقتطاع النص عند حدود الكلمة (§4).
// تُرجع النص كما هو إن كان أقصر من n، وإلا تقصّ عند آخر مسافة قبل n وتُلحق «…».
// ---------------------------------------------------------------------------
function truncate(text: string, n: number): string {
  if (text.length <= n) return text;
  const cut = text.lastIndexOf(' ', n);
  return (cut > 0 ? text.slice(0, cut) : text.slice(0, n)) + '…';
}

// ---------------------------------------------------------------------------
// جلب الدورة الخادمي — مغلّف بـ react.cache لتفادي ازدواج الطلب بين
// generateMetadata والصفحة في نفس دورة الطلب (§3).
// يعيد Course|null ولا يرمي (أي خطأ → null).
// ---------------------------------------------------------------------------
const getCourse = cache(async (slug: string): Promise<Course | null> => {
  try {
    const res = await fetch(`${API_BASE}/catalog/courses/${slug}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store', // يضمن التصيير الديناميكي عند الطلب
    });
    if (!res.ok) return null;
    const json = await res.json() as { data: Course };
    return json.data ?? null;
  } catch {
    return null;
  }
});

// ---------------------------------------------------------------------------
// بناء كائن JSON-LD وفق §5 (Course + Offer + BreadcrumbList + aggregateRating الشرطي).
// ---------------------------------------------------------------------------
function courseJsonLd(course: Course, site: string): object {
  // السعر بالوحدات الرئيسية: price_minor / 100 (§5 — قاعدة السعر الإلزامية).
  const isFree = course.pricing_type === 'free';
  const priceStr = isFree ? '0' : (course.price_minor / 100).toFixed(2);

  // عُقدة Course الأساسية.
  const courseNode: Record<string, unknown> = {
    '@type': 'Course',
    'name': course.title,
    // description لا تكون فارغة — احتياطي إلى title إن غابت الحقول.
    'description': truncate(course.description ?? course.summary ?? course.title, 5000),
    'url': `${site}/catalog/${course.slug}`,
    'inLanguage': 'ar',
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
    'offers': {
      '@type': 'Offer',
      'category': isFree ? 'free' : 'paid',
      'price': priceStr,
      'priceCurrency': 'SAR',
      'availability': 'https://schema.org/InStock',
      'url': `${site}/catalog/${course.slug}`,
    },
  };

  // image — يُحذف المفتاح إن لا غلاف (§5 — قواعد الحذف الشرطي).
  if (course.cover_image) {
    courseNode['image'] = absImage(course.cover_image);
  }

  // aggregateRating — تُضاف فقط حين reviews_count > 0 و rating != null (§5).
  if ((course.reviews_count ?? 0) > 0 && course.rating != null) {
    courseNode['aggregateRating'] = {
      '@type': 'AggregateRating',
      'ratingValue': String(course.rating),
      'reviewCount': String(course.reviews_count),
      'bestRating': '5',
      'worstRating': '1',
    };
  }

  return {
    '@context': 'https://schema.org',
    '@graph': [
      courseNode,
      {
        '@type': 'BreadcrumbList',
        'itemListElement': [
          { '@type': 'ListItem', 'position': 1, 'name': 'الرئيسية',  'item': site },
          { '@type': 'ListItem', 'position': 2, 'name': 'الكتالوج',  'item': `${site}/catalog` },
          { '@type': 'ListItem', 'position': 3, 'name': course.title, 'item': `${site}/catalog/${course.slug}` },
        ],
      },
    ],
  };
}

// ---------------------------------------------------------------------------
// generateMetadata — params غير متزامن في Next.js 15 (§4).
// ---------------------------------------------------------------------------
export async function generateMetadata(
  { params }: { params: Promise<{ slug: string }> },
): Promise<Metadata> {
  const { slug } = await params;
  const course = await getCourse(slug);

  // حالة الغياب — ميتاداتا آمنة بلا canonical (§4).
  if (!course) {
    return {
      title: 'الدورة غير متوفّرة — منصة MOOC',
      description: 'لم نعثر على هذه الدورة.',
    };
  }

  const title = `${course.title} — منصة MOOC`;
  const description = truncate(course.description ?? course.summary ?? '', 160);
  const url = `${SITE}/catalog/${course.slug}`;

  // بناء قوائم الصور الشرطية (تُحذف إن لا cover_image).
  const images = course.cover_image ? [absImage(course.cover_image)] : undefined;

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
// الغلاف الخادمي الرئيسي — Server Component افتراضي (§5).
// ---------------------------------------------------------------------------
export default async function CourseDetailPage(
  { params }: { params: Promise<{ slug: string }> },
) {
  const { slug } = await params;
  const course = await getCourse(slug);

  // دورة غير موجودة → 404 (§5).
  if (!course) notFound();

  return (
    <>
      {/* حقن JSON-LD وفق §5 */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(courseJsonLd(course, SITE)) }}
      />
      {/* تمرير الدورة كاملة إلى الجزيرة التفاعلية */}
      <CourseDetailClient course={course} />
    </>
  );
}
