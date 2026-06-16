# عقد الدفعة B1 — تحسين SEO لصفحة الدورة

- الحالة: معتمد للتنفيذ
- التاريخ: 2026-06-15
- المعماري: architect
- النطاق: صفحة الدورة فقط `frontend/src/app/catalog/[slug]/page.tsx`. لا تشمل المسارات (`/paths`) ولا الأخبار (`/news`) — تلك الدفعة B2.

---

## 1) المشكلة والهدف

صفحة الدورة الحالية مكوّن عميل (`'use client'`) يجلب الدورة عبر `useEffect` من
`GET /api/v1/catalog/courses/{slug}`. النتيجة: لا SSR، لا `metadata` خاصّ بكل دورة،
لا JSON-LD — أضعف جانب SEO في المنصّة.

الهدف: تحويل الصفحة إلى نمط **Server Component shell + Client island**:
- غلاف خادمي (Server Component) ينفّذ `generateMetadata` ويحقن JSON-LD ويُصيَّر خادمياً عند الطلب (dynamic SSR).
- جزيرة عميل تفاعلية (`CourseDetailClient.tsx`) تحوي منطق `enroll`/الحالة كما هو، وتتلقّى الدورة الأولية كـ prop.

لا تغيير في تجربة المستخدم المرئية ولا في سلوك التسجيل.

---

## 2) حدود الموديول والقرارات المعمارية

- السياق المحدود: **Catalog** (القراءة العامّة للدورة). لا يلمس هذا العقد منطق المجال.
- **لا تغيير خلفي مطلوب.** `app/Http/Resources/CourseResource.php` يُصدِّر بالفعل كل ما يلزم JSON-LD:
  `title, slug, summary, description, cover_image, pricing_type, price_minor, rating, reviews_count, published_at, category, instructor.name, sections`.
  (انظر §6 لتأكيد التغطية حقلاً بحقل.)
- **لا تغيير على `sitemap.ts` ولا `robots.ts` في B1** — كلاهما يغطّي الدورات أصلاً. `canonical`/OG على مستوى الصفحة فقط.
- التجارة (`payments.enabled`): خارج نطاق هذا العقد. سعر JSON-LD مشتقّ من بيانات الدورة العامّة فقط ولا يفترض تفعيل الدفع.
- الطبقات: التغيير كلّه في طبقة العرض (Next.js)، لا يمسّ Domain/Application/Infrastructure في الخلفية.

---

## 3) عقد الجلب الخادمي

دالة جلب خادمية واحدة تُعاد للاستخدام بين `generateMetadata` والصفحة (لتفادي ازدواج الطلب يُفضّل
تغليفها بـ `react.cache`):

- التوقيع المنطقي: `getCourse(slug: string): Promise<Course | null>`.
- المصدر: `GET ${NEXT_PUBLIC_API_BASE}/catalog/courses/{slug}` (نفس مسار الواجهة الحالي، عام، بلا مصادقة).
  ثابت القاعدة: `API_BASE` المُصدَّر من `@/lib/api` (`process.env.NEXT_PUBLIC_API_BASE || 'http://localhost:8080/api/v1'`).
- الترويسة: `Accept: application/json` فقط. **بلا** `Authorization` (عام). استخدام `fetch` المباشر على الخادم
  مقبول؛ ودالة `api(path, { auth: false })` آمنة للتشغيل الخادمي لأنها لا تقرأ `localStorage` عند `auth: false`.
- شكل الاستجابة: `{ data: Course }` ⟸ يُعاد `json.data`.
- سياسة التخزين المؤقّت/التصيير: الصفحة **ديناميكية تُصيَّر عند الطلب**. يُحقَّق ذلك بأحد:
  `export const dynamic = 'force-dynamic'` أو `fetch(..., { cache: 'no-store' })`.
  المعيار الملزِم: لا تُولَّد الصفحة ثابتة وقت البناء (انظر معيار القبول 5).
- معالجة الفشل: أي خطأ شبكة/استجابة غير ناجحة (بما فيها 404) ⟸ تُعيد الدالة `null` (لا ترمي).

---

## 4) عقد `generateMetadata`

التوقيع: `generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata>`
(في Next.js 15 `params` غير متزامن — يجب `await`).

ثابت الموقع: `SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000'` (نفس المستخدم في `sitemap.ts`).

> ملاحظة: لا يوجد `metadataBase` في `layout.tsx`، لذا كل الروابط في الميتاداتا **يجب أن تكون مطلقة** (مبنيّة من `SITE`).

### عند غياب الدورة (`getCourse` تعيد `null`)
تُعاد ميتاداتا افتراضية آمنة دون `canonical`:
```
{ title: 'الدورة غير متوفّرة — منصة MOOC',
  description: 'لم نعثر على هذه الدورة.' }
```
(والصفحة نفسها تستدعي `notFound()` — انظر §5.)

### عند وجود الدورة
| الحقل | القيمة |
|---|---|
| `title` | `` `${course.title} — منصة MOOC` `` |
| `description` | وصف مقتطع: `truncate(course.description ?? course.summary ?? '', 160)` (قصّ على حدود الكلمة + «…») |
| `alternates.canonical` | `` `${SITE}/catalog/${course.slug}` `` (مطلق) |
| `openGraph.title` | نفس `title` |
| `openGraph.description` | نفس `description` |
| `openGraph.url` | `` `${SITE}/catalog/${course.slug}` `` |
| `openGraph.type` | `'website'` |
| `openGraph.locale` | `'ar_SA'` |
| `openGraph.siteName` | `'منصة MOOC'` |
| `openGraph.images` | إن وُجد `cover_image`: `[absImage(course.cover_image)]` وإلا تُحذف المفتاح |
| `twitter.card` | `'summary_large_image'` |
| `twitter.title` | نفس `title` |
| `twitter.description` | نفس `description` |
| `twitter.images` | إن وُجد `cover_image`: `[absImage(course.cover_image)]` وإلا تُحذف |

دالة مساعدة `absImage(path)`: إن كان `path` يبدأ بـ `http` يُعاد كما هو، وإلا `` `${SITE}${path.startsWith('/') ? '' : '/'}${path}` ``.
دالة `truncate(text, n)`: تُرجع النص كما هو إن كان أقصر من `n`، وإلا تقصّ عند آخر مسافة قبل `n` وتُلحق «…».

---

## 5) عقد الغلاف الخادمي (`page.tsx`)

- مكوّن خادمي افتراضي `async function CourseDetailPage({ params })`.
- `const { slug } = await params; const course = await getCourse(slug);`
- إن `course === null` ⟸ `notFound()` (يستدعي `not-found.tsx`/سلوك 404 الافتراضي).
- يحقن سكربت JSON-LD داخل الصفحة:
  ```tsx
  <script type="application/ld+json"
    dangerouslySetInnerHTML={{ __html: JSON.stringify(courseJsonLd(course, SITE)) }} />
  ```
- يمرّر الدورة كاملة إلى الجزيرة: `<CourseDetailClient course={course} />`.
- لا منطق تفاعلي في هذا الملف.

### عقد JSON-LD (مفاتيح schema.org الدقيقة)

كائن واحد على هيئة `@graph` يضمّ ثلاث عُقَد: `Course` و`BreadcrumbList`، مع تضمين `Offer` داخل `Course`.

```jsonc
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Course",
      "name": "<course.title>",
      "description": "<truncate(course.description ?? course.summary ?? '', 5000)>",
      "url": "<SITE>/catalog/<course.slug>",
      "inLanguage": "ar",
      "image": "<absImage(course.cover_image)>",      // يُحذف المفتاح إن لا غلاف
      "provider": {
        "@type": "Organization",
        "name": "منصة MOOC",
        "url": "<SITE>"
      },
      "hasCourseInstance": {
        "@type": "CourseInstance",
        "courseMode": "online",
        "inLanguage": "ar"
      },
      "offers": {
        "@type": "Offer",
        "category": "<'free' إن pricing_type==='free' وإلا 'paid'>",
        "price": "<السعر بالوحدات الرئيسية>",          // انظر قاعدة السعر أدناه
        "priceCurrency": "SAR",
        "availability": "https://schema.org/InStock",
        "url": "<SITE>/catalog/<course.slug>"
      },
      // تُضاف فقط حين reviews_count > 0 و rating != null:
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "<course.rating>",
        "reviewCount": "<course.reviews_count>",
        "bestRating": "5",
        "worstRating": "1"
      }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "الرئيسية",  "item": "<SITE>" },
        { "@type": "ListItem", "position": 2, "name": "الكتالوج",  "item": "<SITE>/catalog" },
        { "@type": "ListItem", "position": 3, "name": "<course.title>", "item": "<SITE>/catalog/<course.slug>" }
      ]
    }
  ]
}
```

**قاعدة السعر (إلزامية):**
- النقود تُخزَّن دائماً بوحدات صغرى (`price_minor`). قيمة `Offer.price` بالوحدات الرئيسية:
  `(course.price_minor / 100).toFixed(2)` كسلسلة نصّية (مثال: `"199.00"`).
- إن `course.pricing_type === 'free'` ⟸ `price: "0"` و`category: "free"`.
- `priceCurrency` ثابت `"SAR"` (متسق مع `formatMinor`).

**قواعد الحذف الشرطي (لتجنّب schema مكسور):**
- لا `image` إن لم يوجد `cover_image`.
- لا `aggregateRating` إن `reviews_count === 0` أو `rating == null`.
- `description` لا تكون فارغة؛ إن غابت الحقول كلّها استُخدم `course.title` كقيمة احتياطية.

---

## 6) تأكيد تغطية حقول الـ API (لا تغيير خلفي)

كل حقل يحتاجه JSON-LD/الميتاداتا موجود في `CourseResource::toArray` ونوع `Course` في `frontend/src/lib/types.ts`:

| حقل JSON-LD/Metadata | مصدره في الاستجابة | الحالة |
|---|---|---|
| `name` / `title` | `title` | موجود |
| `description` | `description` ← fallback `summary` | موجود |
| `url` / `canonical` | `slug` | موجود |
| `image` | `cover_image` | موجود (نسبي → يُجعل مطلقاً في الواجهة) |
| `provider` | ثابت «منصة MOOC» | لا يحتاج API |
| `Offer.price` | `price_minor` | موجود |
| `Offer.category` | `pricing_type` | موجود |
| `aggregateRating.ratingValue` | `rating` | موجود (مدوّر لرقم عشري واحد) |
| `aggregateRating.reviewCount` | `reviews_count` | موجود |
| اسم المدرّب (اختياري للعرض) | `instructor.name` | موجود (whenLoaded) |
| `published_at` | `published_at` | موجود (غير مستخدم في B1، متاح) |

**القرار: لا تغيير على `CourseResource.php` ولا أي مورد خلفي في B1.**
ملاحظة لـ backend-dev: لا عمل مطلوب منك في هذه الدفعة. أكِّد فقط أن نقطة `GET /catalog/courses/{slug}`
العامّة تُحمِّل علاقتي `instructor` و`category` و`sections` (كما يعتمد عليه العرض الحالي) — لا تغيير.

---

## 7) عقد الجزيرة العميلة (`CourseDetailClient.tsx`)

- ملف جديد بجوار الصفحة، يبدأ بـ `'use client'`.
- التوقيع: `export function CourseDetailClient({ course }: { course: Course })`.
- ينقل **كل** JSX التفاعلي الحالي من `page.tsx` (Hero, المنهج, بطاقة التسجيل اللاصقة, التقييمات) كما هو.
- يُلغى `useEffect` للجلب و`useState<Course | null>` للدورة — الدورة تأتي كـ prop جاهزة (لا وميض/إعادة جلب).
- يبقى منطق `enroll`/`busy`/`msg`/`useAuth`/`useRouter` بلا تغيير وظيفي.
- يُلغى حارس `if (!course) return ...loading` (لم يعد لازماً، الدورة مضمونة).
- يُحفظ نفس نص مسار التنقّل المرئي (`الرئيسية ‹ الكتالوج ‹ العنوان`) — منفصل عن BreadcrumbList الخاص بـ JSON-LD.

---

## 8) معايير القبول (قابلة للاختبار)

1. **الميتاداتا:** لدورة موجودة، `generateMetadata` تُخرج `title` = «<عنوان> — منصة MOOC»، `description` مقتطعاً (≤ ~160 حرفاً)، `alternates.canonical` = `${SITE}/catalog/<slug>` مطلقاً، و`openGraph` (title/description/url/type=website/locale=ar_SA + image عند توفّر cover_image)، و`twitter.card='summary_large_image'`.
2. **JSON-LD صالح:** الصفحة تحقن `<script type="application/ld+json">` يحوي `Course` + `Offer` (بسعر بالوحدات الرئيسية و`priceCurrency:"SAR"`) + `BreadcrumbList`، و`aggregateRating` فقط حين وجود تقييمات. الناتج JSON صالح يجتاز Rich Results Test / Schema Markup Validator دون أخطاء.
3. **دورة غير موجودة:** slug غير موجود ⟸ `getCourse` تعيد `null` ⟸ الصفحة تستدعي `notFound()` (404)، والميتاداتا الافتراضية بلا `canonical`.
4. **لا تراجع تفاعلي:** زر التسجيل يعمل (مستخدم مسجّل ⟸ POST enroll ⟸ تحويل إلى `/learn/<slug>`؛ غير مسجّل ⟸ `/login`)، وروابط الشراء/المجتمع/التقييمات تعمل كما قبل.
5. **بناء وتصيير:** `npm run build` ينجح، والصفحة تبقى ديناميكية (تُصيَّر خادمياً عند الطلب، لا توليد ثابت وقت البناء) — يُتحقَّق من مخرجات البناء (الصفحة مُعلَّمة `ƒ`/Dynamic لا `○`/Static).

---

## 9) تقسيم المسؤوليات

| الدور | المهمّة |
|---|---|
| **backend-dev** | لا عمل. تأكيد فقط أن `GET /catalog/courses/{slug}` العام يُحمّل `instructor`/`category`/`sections` (قائم). |
| **frontend-dev** | تقسيم الخادم/العميل: تحويل `page.tsx` إلى Server Component، إنشاء `CourseDetailClient.tsx`، كتابة `getCourse` (مع `cache`)، `generateMetadata`، حاقن JSON-LD (`courseJsonLd`)، الدوال المساعدة `absImage`/`truncate`، ضبط الصفحة ديناميكية. |
| **qa-tester** | لا حقول API جديدة ⟸ لا اختبار خلفي إضافي. اختبار/تحقّق واجهة: التحقق من مخرجات `generateMetadata`، صحّة JSON-LD عبر مدقّق schema.org، حالة 404، عدم تراجع التسجيل، نجاح `npm run build` وبقاء الصفحة ديناميكية. |
| **compliance** | التحقق من صحّة schema.org (Course/Offer/BreadcrumbList) و`inLanguage:"ar"` و`og:locale=ar_SA`؛ التأكد من توافق RTL/`lang=ar` الجذري؛ PDPL: JSON-LD/OG لا يكشف بيانات شخصية لمتعلّمين (اسم المدرّب بيانات عامّة منشورة فقط، لا بريد/هوية). |

---

## 10) خارج النطاق (صراحةً)

- المسارات (`/paths/[slug]`) والأخبار (`/news/[slug]`) — تُعالَج في B2.
- أي تعديل على `sitemap.ts`/`robots.ts`/`layout.tsx`.
- إضافة `metadataBase` عمومياً (يُكتفى بروابط مطلقة في B1؛ يمكن طرحه كتحسين لاحق).
- صور OG مُولّدة ديناميكياً (`opengraph-image`) — تحسين مستقبلي، ليس شرطاً للقبول.
