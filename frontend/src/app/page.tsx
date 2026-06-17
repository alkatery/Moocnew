// غلاف خادمي (Server Component) — يحقن JSON-LD ثابت للرئيسية ثم يصيَّر الجزيرة.
// العقد: B2-content-seo.md §6.1 و§6.2
// لا 'use client' — هذا Server Component يظهر static في البناء.

import { HomeClient } from './HomeClient';

// ثابت الموقع — نفس المستخدم في جميع الصفحات (لا metadataBase، كل روابط مطلقة).
const SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000';

// ---------------------------------------------------------------------------
// JSON-LD ثابت: Organization + WebSite مع SearchAction (§6.2).
// ملاحظة §6.أ: لا يوجد logo.png في public/ ⟸ مفتاح logo محذوف كاملاً
// من Organization (لا يُشار إلى مسار شعار غير موجود).
// ---------------------------------------------------------------------------
function homeJsonLd(site: string): object {
  return {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'Organization',
        '@id': `${site}/#organization`,
        'name': 'منصة MOOC',
        'url': site,
        // logo محذوف — لا أصل شعار عام في public/ (§6.أ)
      },
      {
        '@type': 'WebSite',
        '@id': `${site}/#website`,
        'name': 'منصة MOOC',
        'url': site,
        'inLanguage': 'ar',
        'publisher': { '@id': `${site}/#organization` },
        'potentialAction': {
          '@type': 'SearchAction',
          'target': {
            '@type': 'EntryPoint',
            'urlTemplate': `${site}/catalog?q={search_term_string}`,
          },
          'query-input': 'required name=search_term_string',
        },
      },
    ],
  };
}

// ---------------------------------------------------------------------------
// الغلاف الخادمي الرئيسي — Server Component (§6.1).
// تبقى الصفحة ثابتة (static) لأن JSON-LD لا يعتمد على جلب ديناميكي.
// ---------------------------------------------------------------------------
export default function HomePage() {
  return (
    <>
      {/* حقن JSON-LD وفق §6.2 */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(homeJsonLd(SITE)) }}
      />
      {/* الجزيرة التفاعلية — كل المحتوى والمنطق الحالي بلا أي تغيير */}
      <HomeClient />
    </>
  );
}
