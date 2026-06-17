// غلاف خادمي (Server Component) — ينفّذ generateMetadata ويحقن JSON-LD ويُصيَّر عند الطلب.
// العقد: B2-content-seo.md §4
// لا جزيرة عميل — الصفحة عرض فقط، formatDate آمن خادمياً (§4.1).

import { cache } from 'react';
import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import Link from 'next/link';
import { API_BASE } from '@/lib/api';
import type { NewsPost } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';

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
// جلب الخبر خادمياً — بلا Authorization (النقطة عامّة §1.ب).
// مغلَّف بـ react.cache لتفادي ازدواج الطلب بين generateMetadata والصفحة.
// يعيد NewsPost|null ولا يرمي (أي خطأ/404 → null).
// ---------------------------------------------------------------------------
const getNews = cache(async (slug: string): Promise<NewsPost | null> => {
  try {
    const res = await fetch(`${API_BASE}/content/news/${slug}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store', // يضمن التصيير الديناميكي عند الطلب
    });
    if (!res.ok) return null;
    const json = await res.json() as { data: NewsPost };
    return json.data ?? null;
  } catch {
    return null;
  }
});

// ---------------------------------------------------------------------------
// بناء كائن JSON-LD وفق §4.4:
//   NewsArticle + BreadcrumbList
// قواعد الحذف الشرطي:
//   - لا datePublished/dateModified إن published_at === null
//   - لا author إن غاب post.author?.name
//   - لا image (لا مصدر — §4.4)
//   - لا logo في publisher (لا أصل شعار عام — §6.أ)
// ---------------------------------------------------------------------------
function newsJsonLd(post: NewsPost, site: string): object {
  // عُقدة المقال الأساسية
  const articleNode: Record<string, unknown> = {
    '@type': 'NewsArticle',
    // headline ≤110 وفق توصية schema.org (§4.4)
    'headline': truncate(post.title, 110),
    // description لا تكون فارغة — احتياطي إلى title إن غابت الحقول
    'description': truncate(post.excerpt ?? post.body ?? post.title, 5000),
    'url': `${site}/news/${post.slug}`,
    'inLanguage': 'ar',
    // publisher بلا logo — لا أصل شعار عام مؤكَّد (§6.أ)
    'publisher': {
      '@type': 'Organization',
      'name': 'منصة MOOC',
      'url': site,
    },
  };

  // datePublished/dateModified — يُحذفان إن published_at === null (§4.4)
  if (post.published_at) {
    articleNode['datePublished'] = post.published_at;
    // لا حقل updated_at متاح — نستخدم نفس القيمة (§4.4)
    articleNode['dateModified'] = post.published_at;
  }

  // author — يُحذف الكائن كاملاً إن لا author.name (§4.4)
  if (post.author?.name) {
    articleNode['author'] = {
      '@type': 'Person',
      'name': post.author.name,
    };
  }

  return {
    '@context': 'https://schema.org',
    '@graph': [
      articleNode,
      {
        '@type': 'BreadcrumbList',
        'itemListElement': [
          { '@type': 'ListItem', 'position': 1, 'name': 'الرئيسية', 'item': site },
          { '@type': 'ListItem', 'position': 2, 'name': 'الأخبار',  'item': `${site}/news` },
          { '@type': 'ListItem', 'position': 3, 'name': post.title,  'item': `${site}/news/${post.slug}` },
        ],
      },
    ],
  };
}

// ---------------------------------------------------------------------------
// generateMetadata — params غير متزامن في Next.js 15 (§4.3).
// ---------------------------------------------------------------------------
export async function generateMetadata(
  { params }: { params: Promise<{ slug: string }> },
): Promise<Metadata> {
  const { slug } = await params;
  const post = await getNews(slug);

  // حالة الغياب — ميتاداتا آمنة بلا canonical (§4.3)
  if (!post) {
    return {
      title: 'الخبر غير متوفّر — منصة MOOC',
      description: 'لم نعثر على هذا الخبر.',
    };
  }

  const title = `${post.title} — منصة MOOC`;
  const description = truncate(post.excerpt ?? post.body ?? '', 160);
  const url = `${SITE}/news/${post.slug}`;

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
      // نوع المحتوى: مقال إخباري (§4.3)
      type: 'article',
      locale: 'ar_SA',
      siteName: 'منصة MOOC',
      // publishedTime — يُحذف إن null (§4.3)
      ...(post.published_at ? { publishedTime: post.published_at } : {}),
    },
    // لا صورة OG — NewsPost لا يحوي صورة غلاف (§4.3)
    twitter: {
      card: 'summary_large_image',
      title,
      description,
    },
  };
}

// ---------------------------------------------------------------------------
// الغلاف الخادمي الرئيسي — Server Component (§4.1).
// يُصيَّر المقال مباشرةً بلا جزيرة عميل.
// ---------------------------------------------------------------------------
export default async function NewsDetailPage(
  { params }: { params: Promise<{ slug: string }> },
) {
  const { slug } = await params;
  const post = await getNews(slug);

  // خبر غير موجود أو مسوّدة → 404 (§4.2)
  if (!post) notFound();

  return (
    <>
      {/* حقن JSON-LD وفق §4.4 */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(newsJsonLd(post, SITE)) }}
      />

      <article className="mx-auto max-w-3xl">
        <Link className="text-sm font-semibold" href="/news">
          ‹ {t('news.back')}
        </Link>

        <header className="mt-4 overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-700 to-brand-500 p-8 text-white shadow-card">
          <time
            className="badge bg-white/15 text-white"
            dateTime={post.published_at ?? undefined}
          >
            {formatDate(post.published_at)}
          </time>
          <h1 className="mt-3 text-3xl font-extrabold leading-snug text-white">
            {post.title}
          </h1>
          {post.author?.name && (
            <p className="mt-2 text-sm text-brand-100">بقلم {post.author.name}</p>
          )}
        </header>

        <div className="card mt-5 whitespace-pre-line text-base leading-9 text-slate-700">
          {post.body}
        </div>
      </article>
    </>
  );
}
