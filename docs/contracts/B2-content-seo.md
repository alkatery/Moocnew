# عقد الدفعة B2 — تعميم SEO على المسارات والأخبار والمدرّبين + بيانات الموقع

- الحالة: معتمد للتنفيذ
- التاريخ: 2026-06-15
- المعماري: architect
- النموذج المرجعي: `docs/contracts/B1-course-seo.md` و`frontend/src/app/catalog/[slug]/page.tsx` (نمط Server shell + Client island + generateMetadata + JSON-LD، روابط مطلقة من `NEXT_PUBLIC_SITE_URL`).
- النطاق: أربع صفحات فقط — المسار `paths/[slug]`، الخبر `news/[slug]`، المدرّب `instructors/[id]`، والرئيسية `page.tsx`. لا لمس لأي صفحة أخرى.

---

## 0) قرار التقسيم (إلزامي للمراجعة)

يُقسَّم B2 إلى دفعتين فرعيتين لانضباط المراجعة، لأن كل صفحة نمط مستقلّ بمخاطر مختلفة (المسار يحوي بيانات `viewer` خاصّة بالمستخدم تتطلّب انتباهاً، والمدرّب يحوي روابط تواصل تتطلّب فحص PDPL):

- **B2a — المسار + الرئيسية:** تحويل `paths/[slug]/page.tsx` إلى نمط الغلاف/الجزيرة (هو الأعلى مخاطرة لوجود منطق التحاق ومنطق `viewer`)، وحقن `Organization`/`WebSite` في الرئيسية (تغيير بسيط منخفض المخاطرة، مناسب كزوج مع المسار).
- **B2b — الأخبار + المدرّب:** كلاهما صفحة عرض للقراءة فقط بلا منطق تفاعلي، تحويل مباشر منخفض المخاطرة.

يجوز لـ frontend-dev تنفيذ B2a و B2b في PRين منفصلين. هذا العقد الواحد يغطّيهما معاً؛ كل قسم يوسم بدفعته الفرعية.

---

## 1) التحقّقات الحرجة (نتائج الفحص — تُلزِم القرارات أدناه)

### 1.أ — نقطة المسار **عامّة** (لا حاجة لعمل خلفي)
`routes/api.php:251`:
```php
Route::get('paths/{path}', [PathController::class, 'show'])->name('paths.show');
```
خارج مجموعة `auth:sanctum` (التي تبدأ في `:253`). و`PathController::show` يقرأ المستخدم اختيارياً عبر `$request->user('sanctum')` ويتعامل مع غيابه بأمان: `viewer.enrolled=false`, `viewer.completed=false`, `viewer.progress=null`, وكل `item.state` يُحسب بمستخدم `null`.

**القرار: النقطة تُستهلَك خادمياً مباشرةً بلا توكن.** صفحة المسار الحالية تستدعيها بـ `auth: true` بلا داعٍ — يُزال ذلك في B2a. **لا عمل على backend-dev في B2** (ولا حتى إتاحة نقطة قراءة جديدة كما طُرح احتياطياً — غير لازم).

> ملاحظة سلامة بيانات: عند الجلب الخادمي للمسار **بلا توكن**، تأتي `viewer`/`state` بقيم الزائر فقط — لا تسرّب JSON-LD ولا الميتاداتا أي بيانات خاصّة بمستخدم. هذا مرغوب لأن JSON-LD يجب أن يصف الصفحة العامّة لا حالة فرد.

الحقول العامّة المتاحة من `show` (مؤكَّدة من المتحكّم `:96-112`):
`id, title, slug, summary, description, cover_image, published_at, levels[].level, levels[].items[].course{id,title,slug,summary,pricing_type,price_minor,instructor}`.

### 1.ب — نقطة الخبر **عامّة**
`routes/api.php:189`: `GET content/news/{news}` عام؛ `NewsController::show` يُسقِط المسودّات لغير المخوّلين (`abort_unless(isPublished || canManage, 404)`). يستخدم `NewsPostResource`:
`id, title, slug, excerpt, body, published_at, author{id?,name?}, created_at`.

### 1.ج — نقطة المدرّب **عامّة**
`routes/api.php:170`: `GET profiles/instructors/{user}` عام؛ `ProfileController::instructor` يُرجع 404 لغير المدرّب. الحقول:
`name, bio, social_links (Record<string,string> أو {} فارغ), stats{courses,learners,rating}, courses[]{title,slug,cover_image,rating}`.

> تنبيه نوع: لاحظ المعامل `{id}` في الواجهة مقابل `{user}` في الباك — يقبل المُعرّف الرقمي للمستخدم. اسم المعامل في الواجهة `id` يبقى كما هو.

---

## 2) الثوابت والدوال المساعدة المشتركة (مطابقة لـ B1)

كل صفحة تعيد استخدام نفس أنماط B1 (تُنسخ محلياً أو تُستخرج إلى وحدة مساعدة صغيرة — قرار تنفيذي لـ frontend-dev، غير ملزِم):

- `SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000'`.
- `API_BASE` من `@/lib/api`.
- `absImage(path)`: إن بدأ بـ `http` يُعاد كما هو، وإلا `` `${SITE}${path.startsWith('/') ? '' : '/'}${path}` ``.
- `truncate(text, n)`: النص كما هو إن `≤ n`، وإلا قصّ عند آخر مسافة قبل `n` + «…».
- **لا `metadataBase`** في `layout.tsx` ⟸ كل الروابط في الميتاداتا/JSON-LD **مطلقة**.
- العملة الثابتة `"SAR"`، النقود بوحدات صغرى (`*_minor`) ⟸ القيمة الرئيسية `(minor/100).toFixed(2)`.
- كل صفحة ديناميكية (`export const dynamic = 'force-dynamic'` + `fetch(..., { cache: 'no-store' })`)، ودالة الجلب مغلّفة بـ `react.cache` وتعيد `null` عند أي فشل (لا ترمي).

---

## 3) [B2a] صفحة المسار — `frontend/src/app/paths/[slug]/page.tsx`

### 3.1 التقسيم
- يصبح `page.tsx` غلافاً خادمياً (Server Component، بلا `'use client'`).
- يُنشأ `frontend/src/app/paths/[slug]/PathDetailClient.tsx` (`'use client'`) يحوي **كل** JSX التفاعلي الحالي ومنطق `join`/`startCourse`/`busy`/`msg`/`useAuth`/`useRouter` و`StateIcon` بلا تغيير وظيفي. التوقيع: `export function PathDetailClient({ path }: { path: PathDetail })`.
- يُلغى من الجزيرة: `useEffect`/`load` للجلب، `useState<PathDetail|null>`، وحارس `if (!path) return loading`. المسار يأتي prop جاهزاً.
- **يبقى منطق إعادة الجلب بعد `join`/`startCourse`:** بما أن الجلب الأولي صار خادمياً بلا توكن، تأتي `viewer` بقيم الزائر. بعد التحاق المستخدم نحتاج تحديث `viewer`/`state`. الحل: تحتفظ الجزيرة بنسخة محليّة `useState<PathDetail>(path)` تُهيّأ من الـ prop، وبعد نجاح `join`/`startCourse` تُعيد جلب `/learning/paths/${slug}` **بـ `auth: true`** (عميلاً) لتحديث الحالة الخاصّة بالمستخدم. هذا يحافظ على السلوك الحالي تماماً دون تسريب بيانات خاصّة في HTML الخادمي. (سلوك `startCourse` بالتحويل إلى `/checkout` أو `/learn` يبقى كما هو.)

### 3.2 جلب خادمي
`getPath(slug): Promise<PathDetail | null>` ← `GET ${API_BASE}/learning/paths/${slug}`، ترويسة `Accept: application/json` فقط (**بلا** Authorization)، `cache: 'no-store'`، `{ data: PathDetail }` ⟸ `json.data`. أي فشل/404 ⟸ `null`.

### 3.3 `generateMetadata`
عند `null` ⟸ `{ title: 'المسار غير متوفّر — منصة MOOC', description: 'لم نعثر على هذا المسار.' }` (بلا canonical)، والصفحة تستدعي `notFound()`.

عند الوجود:
| الحقل | القيمة |
|---|---|
| `title` | `` `${path.title} — منصة MOOC` `` |
| `description` | `truncate(path.description ?? path.summary ?? '', 160)` |
| `alternates.canonical` | `` `${SITE}/paths/${path.slug}` `` |
| `openGraph` | `{ title, description, url:${SITE}/paths/${path.slug}, type:'website', locale:'ar_SA', siteName:'منصة MOOC', images? }` |
| `twitter` | `{ card:'summary_large_image', title, description, images? }` |
| `images` | إن وُجد `path.cover_image`: `[absImage(path.cover_image)]` وإلا يُحذف المفتاح |

### 3.4 JSON-LD — اختيار schema.org (مع التبرير)

**القرار: `EducationalOccupationalProgram` + `ItemList` (للدورات بالترتيب) + `BreadcrumbList`.**

التبرير: المسار **ليس دورة واحدة** بل سلسلة دورات مرتّبة على مستويات تنتهي بشهادة مسار — هذا تعريف برنامج تعليمي لا دورة. `Course` سيكون غير دقيق دلالياً ويتعارض مع عُقَد `Course` المستقلّة لكل دورة في كتالوجها. `EducationalOccupationalProgram` هو النوع القياسي للبرامج التي تجمع وحدات تعليمية، و`ItemList` يمثّل ترتيب الدورات (مع `position`) بشكل أمين لبنية المستويات. لا نضمّن `Offer` على مستوى البرنامج (المسار نفسه ليس له سعر؛ التسعير على مستوى كل دورة) — تجنّباً لـ schema مضلّل.

```jsonc
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "EducationalOccupationalProgram",
      "name": "<path.title>",
      "description": "<truncate(path.description ?? path.summary ?? path.title, 5000)>",
      "url": "<SITE>/paths/<path.slug>",
      "inLanguage": "ar",
      "image": "<absImage(path.cover_image)>",          // يُحذف إن لا غلاف
      "provider": { "@type": "Organization", "name": "منصة MOOC", "url": "<SITE>" },
      "educationalProgramMode": "online",
      "numberOfCredits": <إجمالي عدد الدورات في كل المستويات>,  // عدد صحيح؛ يُحذف إن 0
      "hasCourse": [                                     // عُقدة Course مختصرة لكل دورة، بترتيب المستوى ثم position
        {
          "@type": "Course",
          "name": "<item.course.title>",
          "url": "<SITE>/catalog/<item.course.slug>",
          "provider": { "@type": "Organization", "name": "منصة MOOC", "url": "<SITE>" },
          "hasCourseInstance": { "@type": "CourseInstance", "courseMode": "online", "inLanguage": "ar" }
        }
        // ... لكل دورة في path.levels[*].items[*]
      ]
    },
    {
      "@type": "ItemList",
      "itemListOrder": "https://schema.org/ItemListOrderAscending",
      "numberOfItems": <إجمالي الدورات>,
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "url": "<SITE>/catalog/<slug-الدورة-الأولى>", "name": "<عنوان>" }
        // ... ترقيم متسلسل عبر المستويات بالترتيب (level ثم item.position)
      ]
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "الرئيسية", "item": "<SITE>" },
        { "@type": "ListItem", "position": 2, "name": "المسارات", "item": "<SITE>/paths" },
        { "@type": "ListItem", "position": 3, "name": "<path.title>", "item": "<SITE>/paths/<path.slug>" }
      ]
    }
  ]
}
```

**قواعد البناء:**
- `hasCourse`/`itemListElement` تُبنى بتسطيح `path.levels` المرتّبة (المستوى تصاعدياً ثم `item.position`)، فترتيب `ItemList` يعكس مسار التعلّم الفعلي.
- لا تُضمَّن أسعار الدورات هنا (التسعير يخصّ صفحة الدورة وعُقدة `Course` الكاملة فيها).

**قواعد الحذف الشرطي:**
- لا `image` إن لا `cover_image`.
- لا `numberOfCredits`/`numberOfItems` فارغاً؛ إن لا دورات ⟸ يُحذف `hasCourse` و`ItemList` كاملةً (يبقى `EducationalOccupationalProgram` + `BreadcrumbList`).
- `description` لا تكون فارغة ⟸ احتياطي إلى `path.title`.

### 3.5 ملاحظة نوع (frontend-dev)
نوع `PathDetail` في `frontend/src/lib/types.ts` **لا يحوي `cover_image`** رغم أن الـ API يُرجعه. أضِف `cover_image?: string | null;` إلى `interface PathDetail` (تغيير نوع غير كاسر).

---

## 4) [B2b] صفحة الخبر — `frontend/src/app/news/[slug]/page.tsx`

### 4.1 التقسيم
- `page.tsx` غلاف خادمي؛ يُنشأ `NewsDetailClient.tsx` (`'use client'`) — **اختياري:** صفحة الخبر بلا منطق تفاعلي حقيقي (عرض فقط)، لذا يجوز إبقاء JSX داخل الغلاف الخادمي مباشرةً دون جزيرة. القرار التنفيذي لـ frontend-dev. الأبسط: لا جزيرة — الغلاف الخادمي يصيّر المقال مباشرةً (`formatDate` آمن خادمياً). حالة «الخبر غير موجود» تُستبدل بـ `notFound()`.

### 4.2 جلب خادمي
`getNews(slug): Promise<NewsPost | null>` ← `GET ${API_BASE}/content/news/${slug}` (عام، بلا Authorization، `no-store`). `{ data: NewsPost }`. فشل/404 ⟸ `null` ⟸ `notFound()`.

### 4.3 `generateMetadata`
عند `null` ⟸ `{ title: 'الخبر غير متوفّر — منصة MOOC', description: 'لم نعثر على هذا الخبر.' }`.

عند الوجود:
| الحقل | القيمة |
|---|---|
| `title` | `` `${post.title} — منصة MOOC` `` |
| `description` | `truncate(post.excerpt ?? post.body ?? '', 160)` |
| `alternates.canonical` | `` `${SITE}/news/${post.slug}` `` |
| `openGraph.type` | `'article'` |
| `openGraph` | `{ title, description, url, type:'article', locale:'ar_SA', siteName:'منصة MOOC', publishedTime: post.published_at ?? undefined }` |
| `twitter` | `{ card:'summary_large_image', title, description }` |

(لا صورة OG — `NewsPost` لا يحوي صورة غلاف؛ لا تُضَف صورة مخترَعة.)

### 4.4 JSON-LD — `NewsArticle` + `BreadcrumbList`
```jsonc
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "NewsArticle",
      "headline": "<truncate(post.title, 110)>",      // schema.org يوصي ≤110 لـ headline
      "description": "<truncate(post.excerpt ?? post.body ?? post.title, 5000)>",
      "url": "<SITE>/news/<post.slug>",
      "inLanguage": "ar",
      "datePublished": "<post.published_at>",          // ISO8601؛ يُحذف المفتاح إن null
      "dateModified": "<post.published_at>",            // نفس القيمة؛ يُحذف إن null (لا حقل updated_at متاح)
      "author": {                                       // يُحذف الكائن كاملاً إن لا author.name
        "@type": "Person",
        "name": "<post.author.name>"
      },
      "publisher": {
        "@type": "Organization",
        "name": "منصة MOOC",
        "url": "<SITE>",
        "logo": { "@type": "ImageObject", "url": "<SITE>/logo.png" }   // مسار شعار ثابت — انظر §6.أ
      }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "الرئيسية", "item": "<SITE>" },
        { "@type": "ListItem", "position": 2, "name": "الأخبار",  "item": "<SITE>/news" },
        { "@type": "ListItem", "position": 3, "name": "<post.title>", "item": "<SITE>/news/<post.slug>" }
      ]
    }
  ]
}
```

**قواعد الحذف الشرطي:**
- لا `datePublished`/`dateModified` إن `published_at === null`.
- لا `author` إن غاب `post.author?.name`.
- لا `image` (لا مصدر) — يُترك بلا مفتاح صورة (Rich Results قد يحذّر تحذيراً غير قاطع؛ مقبول لغياب المصدر، لا نخترع صورة).
- `headline`/`description` لا تكونان فارغتين.

---

## 5) [B2b] صفحة المدرّب — `frontend/src/app/instructors/[id]/page.tsx`

### 5.1 التقسيم
- `page.tsx` غلاف خادمي؛ الصفحة عرض فقط (بلا منطق تفاعلي) ⟸ **لا جزيرة لازمة**؛ الغلاف الخادمي يصيّر المحتوى مباشرةً. `PageHeader`/`Stars` مكوّنات عرض آمنة خادمياً ما لم تحوِ تفاعلاً — يبقيان كما هما.
- حالة `missing` تُستبدل بـ `notFound()` (404 الحقيقي بدل نصّ خطأ).

### 5.2 جلب خادمي
`getInstructor(id): Promise<InstructorProfile | null>` ← `GET ${API_BASE}/profiles/instructors/${id}` (عام، بلا Authorization، `no-store`). `{ data: InstructorProfile }`. فشل/404 ⟸ `null` ⟸ `notFound()`.

### 5.3 `generateMetadata`
التوقيع: `({ params }: { params: Promise<{ id: string }> })`.
عند `null` ⟸ `{ title: 'المدرّب غير متوفّر — منصة MOOC', description: 'لم نعثر على هذا المدرّب.' }`.

عند الوجود:
| الحقل | القيمة |
|---|---|
| `title` | `` `${profile.name} — مدرّب في منصة MOOC` `` |
| `description` | `truncate(profile.bio ?? `${profile.name} — مدرّب في منصة MOOC، ${profile.stats.courses} دورة منشورة.`, 160)` |
| `alternates.canonical` | `` `${SITE}/instructors/${id}` `` |
| `openGraph` | `{ title, description, url:${SITE}/instructors/${id}, type:'profile', locale:'ar_SA', siteName:'منصة MOOC' }` |
| `twitter` | `{ card:'summary', title, description }` |

(لا صورة OG — لا صورة شخصية في الاستجابة؛ الواجهة تعرض الحرف الأول فقط. لا نخترع صورة.)

### 5.4 JSON-LD — `ProfilePage` يحوي `Person` + `BreadcrumbList`
```jsonc
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "ProfilePage",
      "mainEntity": {
        "@type": "Person",
        "name": "<profile.name>",
        "jobTitle": "مدرّب",
        "description": "<truncate(profile.bio, 5000)>",   // يُحذف إن لا bio
        "url": "<SITE>/instructors/<id>",
        "worksFor": { "@type": "Organization", "name": "منصة MOOC", "url": "<SITE>" },
        "sameAs": [ "<قيم social_links>" ]                 // مصفوفة روابط؛ يُحذف المفتاح إن فارغة
      }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "الرئيسية", "item": "<SITE>" },
        { "@type": "ListItem", "position": 2, "name": "<profile.name>", "item": "<SITE>/instructors/<id>" }
      ]
    }
  ]
}
```

**قاعدة `sameAs` (PDPL + تنظيف):**
- `social_links` نوعه `Record<string,string>` (قد يكون `{}`). `sameAs = Object.values(social_links)` بعد تصفية: إبقاء **القيم التي تبدأ بـ `http://` أو `https://` فقط** (روابط منصّات عامّة منشورة طوعاً من المدرّب). تُحذف أي قيمة ليست URL مطلقاً (لا أسماء مستخدمين خام، لا بريد، لا هاتف).
- إن نتجت مصفوفة فارغة ⟸ يُحذف مفتاح `sameAs` كاملاً.

**قواعد الحذف الشرطي:**
- لا `description` إن لا `bio`.
- لا `sameAs` إن لا روابط صالحة.
- لا تُدرَج بيانات `stats.learners` ولا قوائم المتعلّمين في JSON-LD (تجميعية، وغير ذات صلة بـ Person؛ نتجنّب أي تلميح لبيانات أفراد).

---

## 6) [B2a] الصفحة الرئيسية — `frontend/src/app/page.tsx`

### 6.1 التقسيم (دون كسر المحتوى الحالي)
- `page.tsx` الحالي مكوّن عميل ضخم (`HomePage`). **لا يُحوَّل منطقه.** بدل ذلك:
  - يُعاد تسمية المحتوى الحالي إلى جزيرة: يُنقل كامل `HomePage` كما هو إلى `frontend/src/app/HomeClient.tsx` (`'use client'`, `export function HomeClient()`) بلا أي تغيير وظيفي.
  - يصبح `page.tsx` غلافاً خادمياً بسيطاً (بلا `'use client'`) يحقن JSON-LD الثابت ثم يصيّر `<HomeClient />`.
- لا `generateMetadata` ديناميكي مطلوب للرئيسية في B2 (ميتاداتا `layout.tsx` العامّة كافية)؛ النطاق هنا **حقن JSON-LD فقط**. (يجوز إضافة `export const metadata` ثابت إن رغب frontend-dev، اختياري غير ملزِم.)
- الصفحة تبقى قابلة للتصيير الثابت — لا حاجة لـ `force-dynamic` هنا لأن JSON-LD ثابت لا يعتمد على جلب.

### 6.2 JSON-LD ثابت — `Organization` + `WebSite`
```jsonc
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "<SITE>/#organization",
      "name": "منصة MOOC",
      "url": "<SITE>",
      "logo": { "@type": "ImageObject", "url": "<SITE>/logo.png" }    // §6.أ
    },
    {
      "@type": "WebSite",
      "@id": "<SITE>/#website",
      "name": "منصة MOOC",
      "url": "<SITE>",
      "inLanguage": "ar",
      "publisher": { "@id": "<SITE>/#organization" },
      "potentialAction": {
        "@type": "SearchAction",
        "target": {
          "@type": "EntryPoint",
          "urlTemplate": "<SITE>/catalog?q={search_term_string}"
        },
        "query-input": "required name=search_term_string"
      }
    }
  ]
}
```
- `SearchAction` يشير إلى `/catalog?q=` (يطابق سلوك بحث الرئيسية الحالي `search()` في `page.tsx:86-89`). مضمّن (مرغوب لتفعيل صندوق بحث Google sitelinks).
- `@id` تربط `WebSite.publisher` بـ `Organization` لرسم بياني نظيف.

### 6.أ — قرار شعار المنظمة (`logo`)
يُستخدم `${SITE}/logo.png` كمرجع ثابت في `Organization`/`publisher`. **شرط:** يجب أن يوجد أصل عام على هذا المسار. إن لم يوجد ملف شعار عام مؤكَّد في `frontend/public/`:
- frontend-dev يضع شعاراً عاماً مناسباً في `frontend/public/logo.png` (PNG، نسبة معقولة، خلفية شفافة/بيضاء)، أو
- إن تعذّر، **يُحذف مفتاح `logo` كاملاً** من `Organization` ومن `publisher` في الخبر والرئيسية (مفتاح اختياري في schema.org؛ غيابه لا يكسر التحقّق). لا يُشار إلى مسار شعار غير موجود.

---

## 7) معايير القبول (قابلة للاختبار)

### عامّة لكل الصفحات الديناميكية (المسار/الخبر/المدرّب)
1. **ميتاداتا صحيحة:** `title` ينتهي بـ «— منصة MOOC» (المدرّب: «— مدرّب في منصة MOOC»)، `description` مقتطع ≤ ~160 حرفاً، `alternates.canonical` مطلق ويطابق مسار الصفحة، `openGraph.locale='ar_SA'` و`siteName='منصة MOOC'` و`type` الصحيح (المسار `website`، الخبر `article`، المدرّب `profile`).
2. **404 حقيقي:** slug/id غير موجود (أو مسوّدة/غير مدرّب) ⟸ دالة الجلب تعيد `null` ⟸ `notFound()` يصيّر صفحة 404، والميتاداتا الافتراضية بلا `canonical`.
3. **JSON-LD صالح:** يُحقن `<script type="application/ld+json">` ويجتاز Schema Markup Validator / Rich Results Test بلا أخطاء، مع `inLanguage:"ar"` و`BreadcrumbList` صحيح.

### خاصّة بالمسار (B2a)
4. JSON-LD = `EducationalOccupationalProgram` + `ItemList` (بترتيب تصاعدي يعكس المستويات) + `BreadcrumbList`؛ لا `Offer` على مستوى البرنامج؛ `hasCourse`/`ItemList` يُحذفان إن لا دورات.
5. **لا تراجع تفاعلي:** زر «انضمّ» يعمل (مستخدم ⟸ POST enroll ثم تحديث الحالة عميلاً؛ زائر ⟸ `/login`)؛ «ابدأ الدورة» يحوّل إلى `/checkout/<slug>` (pending) أو `/learn/<slug>`؛ شريط التقدّم وحالات `completed/unlocked/locked` تظهر صحيحة بعد التحاق المستخدم.
6. **لا تسريب خادمي:** HTML المُصيَّر خادمياً (قبل ترطيب العميل) يُظهر `viewer` بقيم الزائر فقط (لا حالة فرد) — يُتحقَّق بمعاينة المصدر بلا توكن.

### خاصّة بالخبر (B2b)
7. JSON-LD = `NewsArticle` (headline ≤110، datePublished من `published_at`، author إن وُجد، publisher = منظمة) + `BreadcrumbList`؛ تُحذف الحقول الغائبة بلا مفاتيح فارغة. مسودّة ⟸ 404.

### خاصّة بالمدرّب (B2b)
8. JSON-LD = `ProfilePage`→`Person` (name/jobTitle/worksFor) + `BreadcrumbList`؛ `sameAs` يحوي **روابط URL مطلقة فقط** من `social_links` ويُحذف إن فارغ؛ لا بيانات أفراد متعلّمين. غير مدرّب ⟸ 404.

### خاصّة بالرئيسية (B2a)
9. الصفحة تحقن JSON-LD ثابت `Organization` + `WebSite` (+`SearchAction` يشير إلى `/catalog?q=`)، **وكل محتوى الرئيسية الحالي يعمل كما هو** (البحث، الإحصاءات، الأقسام، الدورات، المسارات، الأخبار، نموذج التواصل) بلا أي تراجع بصري أو وظيفي.

### بناء
10. `npm run build` ينجح. المسار/الخبر/المدرّب ديناميكية (`ƒ` لا `○`). الرئيسية تبقى سليمة (ثابتة أو ديناميكية — كلاهما مقبول ما دام JSON-LD محقوناً).

---

## 8) تقسيم المسؤوليات

| الدور | المهمّة |
|---|---|
| **backend-dev** | **لا عمل في B2.** كل النقاط الثلاث عامّة بالفعل (مؤكَّد §1). فقط تأكيد بقاء `paths.show`/`news.show`/`profiles.instructor` عامّة بنفس الحقول. |
| **frontend-dev** | **B2a:** تحويل `paths/[slug]/page.tsx` لغلاف خادمي + `PathDetailClient.tsx` (مع إعادة الجلب العميلة بعد الالتحاق)، إضافة `cover_image` لنوع `PathDetail`، إزالة `auth:true` من جلب المسار الخادمي؛ غلاف الرئيسية + `HomeClient.tsx` + حقن `Organization`/`WebSite`؛ توفير `public/logo.png` أو حذف مفتاح `logo`. **B2b:** تحويل `news/[slug]` و`instructors/[id]` لأغلفة خادمية (بلا جزيرة)، `generateMetadata` + JSON-LD لكلٍّ، `notFound()`. كل الصفحات الديناميكية: `react.cache` + `no-store` + `force-dynamic`. |
| **qa-tester** | لا حقول API جديدة ⟸ لا اختبار خلفي. واجهة: مطابقة معايير §7 لكل صفحة، صحّة JSON-LD عبر مدقّق schema.org، حالات 404، عدم تراجع التحاق المسار وبحث الرئيسية، نجاح `npm run build` وبقاء الصفحات الثلاث ديناميكية. |
| **compliance** | **حرج للمدرّب:** التحقق أن `sameAs` لا يحوي إلا روابط URL عامّة (لا بريد/هاتف/أسماء مستخدمين خام)، وأن JSON-LD/الميتاداتا لا تكشف بيانات أفراد متعلّمين (لا أسماء، لا `learners` كقائمة). **للمسار:** التحقق ألّا يُسرّب الجلب الخادمي بلا توكن أي حالة `viewer` خاصّة في HTML. **عام:** `inLanguage:"ar"` + `og:locale=ar_SA` + توافق RTL/`lang=ar` الجذري؛ صحّة schema.org لكل الأنواع. |

---

## 9) خارج النطاق (صراحةً)

- **لا لمس `sitemap.ts` ولا `robots.ts`** — `sitemap.ts` يغطّي المسارات (`/paths/{slug}`) والأخبار (`/news/{slug}`) أصلاً (`sitemap.ts:35-37`)، والرئيسية في `statics`. صفحات المدرّبين ليست في الـ sitemap حالياً؛ إضافتها **خارج نطاق B2** (تتطلّب نقطة قائمة مدرّبين عامّة غير موجودة — يُطرح كتحسين لاحق).
- إضافة `metadataBase` عمومياً (يُكتفى بروابط مطلقة، كما B1).
- صور OG مولّدة ديناميكياً (`opengraph-image`) لأي صفحة.
- أي تغيير خلفي (موارد/نقاط/أعمدة) — لا شيء مطلوب.
- `generateMetadata` ديناميكي للرئيسية (ميتاداتا `layout.tsx` كافية؛ B2 يحقن JSON-LD فقط في الرئيسية).
