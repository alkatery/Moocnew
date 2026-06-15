# عقد الدفعة B3 — تدقيق WCAG 2.2 AA وخطّة الإصلاح

- الحالة: معتمد للتنفيذ
- التاريخ: 2026-06-15
- المدقّق: compliance
- النطاق: إصلاحات عامّة عالية الأثر تخدم الموقع كلّه (لا إعادة بناء كامل)
- النموذج المرجعي: WCAG 2.2 AA — المعيار الإلزامي للتسليم

---

## 1. الملخّص التنفيذي

فُحص الكود المصدري لـ Next.js 15 RTL/عربي فحصاً يدوياً شاملاً يغطّي عشرة محاور.
الموقع يمتلك أساساً جيداً (دلالات HTML سليمة، نماذج موسومة بـ `<label htmlFor>`, `lang="ar" dir="rtl"` على الجذر، معظم الأيقونات `aria-hidden`)؛ غير أن ستّ فجوات عالية الخطورة تعيق الامتثال الكامل لـ AA.

### نتيجة الفحص العامّة

| المحور | النتيجة |
|---|---|
| Bypass Blocks 2.4.1 | فجوة — لا skip link |
| Focus ظاهر 2.4.7 + 2.4.11 | فجوة — حلقة focus ضعيفة التباين، لا `:focus-visible` عام |
| لوحة المفاتيح 2.1.1 | جزئي — عناصر تفاعلية بلا `aria-pressed/selected` |
| Landmarks 1.3.1 | جزئي — `<main>` بلا id، Nav الرئيسية بلا `aria-label` |
| تباين الألوان 1.4.3 AA | فجوة متعدّدة — text-slate-400 على أبيض = 2.56:1 |
| النماذج 3.3.2 / 4.1.2 | جيّد بالمجمل؛ رسائل الخطأ بلا `role` في أغلب المواضع |
| مناطق حيّة 4.1.3 | جزئي — غير متّسق عبر الصفحات |
| الصور 1.1.1 | مقبول بالمجمل؛ حالة واحدة تستوجب مراجعة |
| اللغة/الاتجاه 3.1.1 | جيّد — `lang="ar" dir="rtl"` على الجذر |
| الحركة prefers-reduced-motion | لا إعداد — توصية (ليست AA إلزامية) |
| فحص آلي | لا اختبار a11y — مطلوب إعداده |

**القرار: يُعاد إلى frontend-dev لإصلاح الفجوات الستّ قبل الإغلاق؛ qa-tester يضيف اختبار axe.**

---

## 2. جدول الفجوات التفصيلي

| # | المعيار WCAG | الموقع في الكود | الخطورة | الدليل | الإصلاح المحدّد |
|---|---|---|---|---|---|
| G1 | 2.4.1 Bypass Blocks | `frontend/src/app/layout.tsx:30` | P1 — حاجب | لا skip link قبل Nav؛ `<main>` بلا `id` | أضف `<a href="#main-content" className="sr-only focus:not-sr-only ...">تخطّى إلى المحتوى</a>` قبل `<Nav>`؛ أضف `id="main-content"` على `<main>` |
| G2 | 2.4.7 / 2.4.11 Focus Appearance | `frontend/src/app/globals.css:31-33` | P1 — حاجب | `.btn` يستخدم `focus:ring-brand-300` (#a5b4fc) → تباين حلقة التركيز 1.99:1 على الأبيض (يلزم ≥ 3:1 وفق WCAG 2.4.11). `.input` يستخدم `focus:ring-brand-200` → 1.49:1. الروابط العادية والـ `.chip` لا تملك أي `:focus-visible` صريح | غيّر ring الـ `.btn` إلى `focus-visible:ring-brand-600` (10:1)؛ غيّر ring الـ `.input` إلى `focus-visible:ring-brand-500` (4.21:1 — يكفي لعنصر غير نصّي)؛ أضف `:focus-visible { outline: 2px solid #1f3a93; outline-offset: 2px; }` عاماً على `a` و`.chip` في globals.css |
| G3 | 1.4.3 Contrast AA | `frontend/src/app/globals.css:40` وأكثر من 40 موضعاً | P1 — حاجب | `text-slate-400` (#94a3b8) على خلفية فاتحة = 2.56:1 (يلزم 4.5:1 للنصّ العادي). المتضرّر: placeholder النموذج، اسم المدرّب في CourseCard، روابط breadcrumb، تبويبات studio غير النشطة، معدّل الترتيب في leaderboard، وأكثر من 40 موقعاً | قاعدة عامّة: استبدل `text-slate-400` بـ `text-slate-500` في كل نصّ معلوماتي على خلفية فاتحة؛ استبدل `placeholder:text-slate-400` بـ `placeholder:text-slate-500` في globals.css. النصوص على خلفية داكنة (footer = slate-900) مقبولة (6.96:1) ولا تُمسّ |
| G4 | 1.4.3 Contrast AA | صفحات: login:77، register:55، account:35 | P1 — حاجب | `text-emerald-600` (#059669) على أبيض = 3.77:1 — أقلّ من 4.5:1 المطلوب للنصّ العادي. يُستخدم لرسائل نجاح inline (resentNote، Note.ok) | غيّر إلى `text-emerald-700` (#047857) → 5.48:1 — تمرير AA |
| G5 | 4.1.3 Status Messages | 25+ موضعاً في الكود | P1 — حاجب | `{error && <p className="error mb-3">}` منتشر في 20+ صفحة ومكوّن بلا `role="alert"` أو `aria-live`. قائمة المواضع: ContactForm:56، ChatPanel:89، LessonEditor:238، AssessmentsPanel:200/282/368، certificates:78، plans:92، redeem:43، admin/quality:69، admin/activity:30، admin/users:125، admin/page:77، admin/content:75، admin/paths:124، admin/news:70، admin/tools:160، checkout:80، login:71، register:99، PathDetailClient:143، survey:99. ملاحظة: صفحة account تستخدم `role="status"` بشكل صحيح في مكوّن `Note` فقط | أضف `role="alert"` على `<p className="error">` (رسائل الخطأ)؛ أضف `role="status"` على رسائل النجاح الـ `<span className="success">`. الحلّ الأمثل: تعديل تعريف `.error` في globals.css لا يكفي — يجب إضافة السمة في JSX. أنشئ مكوّن `<ErrorMsg>` و`<SuccessMsg>` مشتركَين |
| G6 | 1.3.1 / 4.1.2 ARIA | `frontend/src/app/studio/[slug]/page.tsx:169` | P2 — تدهور | أزرار التبويب (المنهج / التقييمات) مبنيّة على `<button>` عادي بلا `role="tab"` و`aria-selected`. أزرار chip في /admin/page:108 و/catalog/page:73 بلا `aria-pressed` — قارئ الشاشة لا يعلم بالحالة المحدّدة. زرّ toggle payment في admin/page:94 يستخدم `role="switch"` بشكل صحيح (ممتاز) لكنه يفتقر `aria-label` واضح للحالة | لمجموعة التبويبات: أضف `role="tablist"` على الحاوي، `role="tab" aria-selected={tab===k}` على كل زرّ. للـ chip: أضف `aria-pressed={isActive}`. أولويّة: التبويبات P1، الـ chip P2 |

---

## 3. ملاحظات إضافية (تدهور طفيف أو توصيات)

### 3.1 Landmarks — مقبولة مع تحسين بسيط
- `<nav className="nav">` في Nav.tsx:38 — لا تملك `aria-label`؛ في حين تملك nav عناصر Footer `aria-label="التعلّم"` و`aria-label="المنصة"` (جيّد). **الإصلاح:** أضف `aria-label="التنقّل الرئيسي"` على `<nav>` في Nav.tsx.
- `<main className="container flex-1">` بلا `id` — مرتبط بـ G1.

### 3.2 الصور — مقبولة مع استثناء واحد
- جميع الصور المحتوائية لها `alt` ذو معنى (CourseCard:24، learn/[slug]:115، instructors/[id]:237).
- الأيقونات الزخرفية SVG لها `aria-hidden` (جيّد).
- `studio/[slug]/page.tsx:187`: صورة غلاف الدورة في الاستوديو `alt=""` — هذا مقبول إذ هي صورة معاينة وليست محتوى تعليمياً. احتفظ به كما هو.
- `studio/LessonEditor.tsx:151`: `alt=""` على صورة asset الدرس — قد تكون معلوماتية في سياق التعديل؛ **اقتراح:** أضف `alt={lesson.title}` في السياق الاستوديوي.

### 3.3 اللغة والاتجاه — جيّد
- الجذر `lang="ar" dir="rtl"` موجود في layout.tsx:17.
- حقول البريد والأرقام لها `dir="ltr"` حيث يلزم (login:66، register:81، account:76).
- البريد الإلكتروني في footer يُعرض `dir="ltr"` ضمنياً كرابط `<a href="mailto:...">`.
- `rtl:rotate-180` على سهم التنقّل في Community (community/[slug]:66) — صحيح للـ RTL.

### 3.4 الحركة — توصية (ليست AA إلزامية)
- `globals.css` و`tailwind.config.ts` لا يتضمّنان `@media (prefers-reduced-motion: reduce)`.
- انتقالات `transition hover:-translate-y-0.5` موجودة في CourseCard:20 و instructors/[id]:233.
- **التوصية (ليست شرطاً لإغلاق B3):** أضف في globals.css:

  ```css
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      animation-duration: 0.01ms !important;
      transition-duration: 0.01ms !important;
    }
  }
  ```

### 3.5 `<html>` بلا `metadataBase` — ليس a11y
مرتبط بـ B1/B2، خارج نطاق B3.

---

## 4. خطّة الإصلاح المرتّبة (frontend-dev)

الإصلاحات مرتّبة من الأعلى أثراً إلى الأدنى؛ كلّها ذات نطاق محدود ومحدّد بالملف والسطر.

### المرحلة 1 — إصلاحات هيكليّة عامّة (يوم واحد)

#### 1.1 Skip Link + Main ID
الملف: `frontend/src/app/layout.tsx`

في `<body>` أضف مباشرة قبل `<SiteContentProvider>`:
```tsx
<a
  href="#main-content"
  className="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4
             focus:z-50 focus:rounded-xl focus:bg-brand-600 focus:px-4 focus:py-2
             focus:text-sm focus:font-bold focus:text-white focus:outline-none
             focus:ring-2 focus:ring-white"
>
  تخطّى إلى المحتوى الرئيسي
</a>
```
وعدّل `<main>` إلى:
```tsx
<main id="main-content" className="container flex-1">
```

#### 1.2 Nav aria-label
الملف: `frontend/src/components/Nav.tsx:38`

عدّل:
```tsx
<nav className="nav" aria-label="التنقّل الرئيسي">
```

#### 1.3 Focus-visible عامّ + إصلاح حلقة التركيز
الملف: `frontend/src/app/globals.css`

في `@layer base` أضف:
```css
a:focus-visible,
button:focus-visible,
[tabindex]:focus-visible {
  outline: 2px solid #1f3a93;
  outline-offset: 2px;
}
```
وعدّل في `@layer components`:
```css
/* من: */
.btn { ... focus:ring-brand-300 ... }
/* إلى: */
.btn { ... focus-visible:ring-2 focus-visible:ring-brand-600 focus:outline-none ... }

/* من: */
.input { ... focus:ring-2 focus:ring-brand-200 ... }
/* إلى: */
.input { ... focus-visible:ring-2 focus-visible:ring-brand-500 focus:outline-none ... }
```
ملاحظة: الانتقال من `focus:` إلى `focus-visible:` يمنع ظهور الحلقة عند النقر بالفأرة ويحافظ عليها للوحة المفاتيح.

### المرحلة 2 — تباين الألوان (نصف يوم)

#### 2.1 placeholder النماذج
الملف: `frontend/src/app/globals.css:40`

```css
/* من: */
placeholder:text-slate-400
/* إلى: */
placeholder:text-slate-500
```

#### 2.2 النصوص المعلوماتية slate-400 على خلفية فاتحة
قاعدة عامّة: كل `text-slate-400` يحمل نصاً معلوماتياً على خلفية بيضاء/slate-50 → يُرقَّى إلى `text-slate-500`.

الملفات ذات الأولوية القصوى (مرئية للمستخدم النهائي):
- `frontend/src/components/CourseCard.tsx:48,53` — اسم المدرّب وعدد التقييمات
- `frontend/src/app/catalog/[slug]/CourseDetailClient.tsx:56,58` — روابط breadcrumb
- `frontend/src/app/paths/[slug]/PathDetailClient.tsx:101,103` — روابط breadcrumb
- `frontend/src/components/PageHeader.tsx:29,34` — روابط breadcrumb
- `frontend/src/app/studio/[slug]/page.tsx:171` — نصّ تبويب غير نشط (ضروري لمعرفة الحالة)
- `frontend/src/app/leaderboard/page.tsx:39` — رقم الترتيب

النصوص التالية مقبولة ولا تُمسّ (زخرفية أو ثانوية أو على خلفية داكنة):
- كل `text-slate-400` في footer (على slate-900 = 6.96:1 — مقبول)
- نصوص `text-xs text-slate-400` الزخرفية مثل أوقات الأحداث، توقيت المنتدى، hints الاستوديو

#### 2.3 رسائل نجاح emerald inline
الملفات: `login/page.tsx:77`، `register/page.tsx:55`، `account/page.tsx:35`

```tsx
/* من: text-emerald-600 → إلى: text-emerald-700 */
className={`mt-3 text-sm ${ok ? 'text-emerald-700' : 'text-rose-600'}`}
```

### المرحلة 3 — مناطق حيّة (aria-live) موحّدة (نصف يوم)

#### 3.1 مكوّن مشترك لرسائل الخطأ والنجاح
أنشئ `frontend/src/components/StatusMessage.tsx`:
```tsx
export function ErrorMsg({ msg }: { msg: string }) {
  if (!msg) return null;
  return <p className="error mb-3" role="alert" aria-live="assertive">{msg}</p>;
}

export function SuccessMsg({ msg }: { msg: string }) {
  if (!msg) return null;
  return <p className="success mb-3" role="status" aria-live="polite">{msg}</p>;
}
```

استبدل في الملفات التالية `{error && <p className="error mb-3">{error}</p>}` بـ `<ErrorMsg msg={error} />`:
- `frontend/src/components/ContactForm.tsx:56`
- `frontend/src/components/ChatPanel.tsx:89`
- `frontend/src/components/Reviews.tsx:62`
- `frontend/src/app/checkout/[slug]/page.tsx:80`
- `frontend/src/app/login/page.tsx:71`
- `frontend/src/app/register/page.tsx:99`
- `frontend/src/app/catalog/[slug]/CourseDetailClient.tsx:142`
- `frontend/src/app/paths/[slug]/PathDetailClient.tsx:143`
- `frontend/src/app/survey/[slug]/page.tsx:99`
- `frontend/src/app/certificates/page.tsx:78`
- `frontend/src/app/plans/page.tsx:92`
- `frontend/src/app/redeem/page.tsx:43`
- كل صفحات admin (7 ملفات)

واستبدل رسائل النجاح الـ `<span className="success">` بـ `<SuccessMsg>` في:
- `frontend/src/components/studio/LessonEditor.tsx:238`
- `frontend/src/app/learn/[slug]/page.tsx:143`
- `frontend/src/app/studio/[slug]/page.tsx:143`

### المرحلة 4 — ARIA للتبويبات والأزرار التبديلية (نصف يوم)

#### 4.1 تبويبات الاستوديو
الملف: `frontend/src/app/studio/[slug]/page.tsx:167-175`

```tsx
<div className="mb-6 flex gap-2 border-b border-slate-200" role="tablist" aria-label="أقسام الاستوديو">
  {([['curriculum', 'المنهج'], ['assessments', 'التقييمات والدرجات']] as const).map(([k, label]) => (
    <button
      key={k}
      role="tab"
      aria-selected={tab === k}
      aria-controls={`tabpanel-${k}`}
      onClick={() => setTab(k)}
      className={...}
    >
      {label}
    </button>
  ))}
</div>
{/* على لوح المحتوى: */}
<div id="tabpanel-curriculum" role="tabpanel" hidden={tab !== 'curriculum'}>...</div>
<div id="tabpanel-assessments" role="tabpanel" hidden={tab !== 'assessments'}>...</div>
```

#### 4.2 أزرار Chip (فلاتر الكتالوج)
الملف: `frontend/src/app/catalog/page.tsx:73,79,86,90`

أضف `aria-pressed={isActive}` على كل `<button className="chip ...">`. مثال:
```tsx
<button
  className={`chip ${category === '' ? 'chip-active' : ''}`}
  aria-pressed={category === ''}
  onClick={() => setCategory('')}
>
  كل المجالات
</button>
```

---

## 5. إعداد الفحص الآلي (qa-tester)

### 5.1 التبعيّات المطلوبة
```bash
cd frontend
npm install --save-dev axe-core jest-axe @axe-core/react
# أو لـ vitest:
npm install --save-dev vitest-axe
```
ملاحظة: المشروع يستخدم vitest 2.1.8 + jsdom + @testing-library/react — البنية التحتية جاهزة.

### 5.2 ملف الاختبار المقترح
أنشئ `frontend/tests/a11y.test.tsx`:

```tsx
import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import { ContactForm } from '@/components/ContactForm';
// import صفحات أخرى مقابلة لمكوّنات Client فقط (لا Server Components)

expect.extend(toHaveNoViolations);

describe('WCAG a11y — المكوّنات الأساسية', () => {
  it('ContactForm لا تحوي انتهاكات axe', async () => {
    const { container } = render(<ContactForm />);
    const results = await axe(container, {
      rules: { 'color-contrast': { enabled: true } },
    });
    expect(results).toHaveNoViolations();
  });

  it('ErrorMsg/SuccessMsg يحملان role صحيح', async () => {
    const { container } = render(
      <div>
        <p className="error" role="alert">خطأ</p>
        <p role="status">تمّ الحفظ</p>
      </div>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});
```

### 5.3 المكوّنات المقترحة للفحص الآلي في B3
| المكوّن / الصفحة | السبب |
|---|---|
| `ContactForm` | نموذج كامل (label، input، button، error) |
| `CourseCard` | بطاقة قابلة للنقر + صورة + نصوص |
| `Nav` | navigation landmark + روابط + زرّ logout |
| `Footer` | nav landmarks + روابط + mailto |
| `StatusMessage` (مكوّن جديد) | role="alert" + role="status" |
| صفحة login | نموذج مصادقة كامل |

### 5.4 دمج في CI
أضف في `package.json`:
```json
"test:a11y": "vitest run tests/a11y.test.tsx"
```
وادمج في pipeline البناء بعد `npm run test`.

---

## 6. معايير القبول القابلة للتحقّق

| # | المعيار | طريقة التحقّق |
|---|---|---|
| AC1 | Skip link يظهر عند ضغط Tab أولاً في أي صفحة ويأخذ إلى `#main-content` | اضغط Tab في المتصفح — يجب أن يظهر الرابط بتصميم واضح |
| AC2 | كل عنصر تفاعلي (a, button, input, select, .chip) يملك حلقة تركيز مرئية بتباين ≥ 3:1 | تنقّل بلوحة المفاتيح في catalog, login, studio — الحلقة ظاهرة في كل عنصر |
| AC3 | لا `text-slate-400` لنصّ معلوماتي على خلفية بيضاء/slate-50 | `grep -rn "text-slate-400"` في src/components + src/app — كل موقع مُبرَّر أو مُبدَّل بـ slate-500 |
| AC4 | placeholder النموذج ≥ 4.5:1 | أداة تباين على input في صفحة login/register/contact |
| AC5 | رسائل الخطأ تُعلن فورياً لقارئ الشاشة | NVDA/VoiceOver: أرسل نموذج فارغ — يُعلَن الخطأ دون تحريك التركيز |
| AC6 | رسائل النجاح تُعلن بـ polite | NVDA/VoiceOver: احفظ الملف الشخصي — تُعلَن "تمّ الحفظ" |
| AC7 | تبويبات الاستوديو تعمل بالسهام | في studio/[slug]: Tab إلى tablist ثم سهمَي Left/Right للتبديل |
| AC8 | اختبار axe أخضر (`npm run test:a11y`) | لا انتهاكات من jest-axe على المكوّنات المدرجة في §5.3 |
| AC9 | `npm run build` يمرّ والأنواع خضراء (`npm run typecheck`) | CI pipeline |
| AC10 | `npm run test` يمرّ (اختبارات موجودة + a11y جديدة) | CI pipeline |

---

## 7. حدود النطاق الصريحة (ما يُؤجَّل)

- **`prefers-reduced-motion`:** توصية مستقبلية، ليست AA إلزامية، خارج B3.
- **اختبار axe عبر Playwright/E2E:** يُدرَج في دفعة QA مستقبلية، ليس شرطاً لإغلاق B3.
- **تبويبات admin/tools + تبويبات أخرى:** إصلاح تبويبات الاستوديو كافٍ لـ B3؛ باقي التبويبات في المرحلة التالية.
- **خاصيّة `focus-within` للقوائم المنسدلة:** لا توجد قوائم منسدلة حاليّاً.
- **تحسينات PDPL/SEO:** خارج نطاق B3 (تُعالَج في A1/A2/A3 و B1/B2).
- **اختبار قارئ الشاشة الكامل (NVDA/VoiceOver):** دور qa-tester، يوثَّق في تقرير QA منفصل.
- **نماذج admin الداخلية** (tools, activity): P2 — تُعالَج بعد تغطية الصفحات العامّة.

---

## 8. تقسيم المسؤوليات

| الدور | المهمّة في B3 |
|---|---|
| **frontend-dev** | تنفيذ المراحل 1-4 كما في §4: skip link، focus-visible، تحديث globals.css، ترقية تباين الألوان، `<ErrorMsg>`/`<SuccessMsg>`، ARIA للتبويبات والـ chip |
| **qa-tester** | إعداد `tests/a11y.test.tsx` (§5.2)، تثبيت `jest-axe`، تشغيل التنقّل اليدوي بلوحة المفاتيح (AC1-AC7)، توثيق نتائج NVDA/VoiceOver |
| **compliance** | التحقّق من AC1-AC10، إعادة قياس تباين الألوان بعد الإصلاح، التحقّق من `role="alert"` بـ devtools، إغلاق B3 |
| **reviewer** | مراجعة PR: لا تغيير في منطق الأعمال، البناء والأنواع والاختبارات خضراء، ثمّ التوقيع |

---

## 9. الملفات المستوجبة التغيير (ملخّص)

| الملف | التغيير |
|---|---|
| `frontend/src/app/layout.tsx` | skip link + `id="main-content"` على `<main>` |
| `frontend/src/app/globals.css` | `:focus-visible` عامّ، `.btn` ring-brand-600، `.input` ring-brand-500، `placeholder:text-slate-500` |
| `frontend/src/components/Nav.tsx` | `aria-label="التنقّل الرئيسي"` |
| `frontend/src/components/StatusMessage.tsx` | ملف جديد: `<ErrorMsg>` + `<SuccessMsg>` |
| `frontend/src/components/CourseCard.tsx` | slate-400 → slate-500 (اسم المدرّب، عدد التقييمات) |
| `frontend/src/components/PageHeader.tsx` | روابط breadcrumb: slate-400 → slate-500 |
| `frontend/src/app/catalog/[slug]/CourseDetailClient.tsx` | روابط breadcrumb: slate-400 → slate-500 |
| `frontend/src/app/paths/[slug]/PathDetailClient.tsx` | روابط breadcrumb: slate-400 → slate-500 |
| `frontend/src/app/studio/[slug]/page.tsx` | tablist/tab/aria-selected + slate-400 tab inactive → slate-500 |
| `frontend/src/app/catalog/page.tsx` | `aria-pressed` على أزرار chip |
| `frontend/src/app/login/page.tsx` | `<ErrorMsg>` بدل `<p className="error">` |
| `frontend/src/app/register/page.tsx` | `<ErrorMsg>` + emerald-600 → emerald-700 |
| `frontend/src/app/account/page.tsx` | Note.ok emerald-600 → emerald-700 |
| `frontend/src/components/ContactForm.tsx` | `<ErrorMsg>` بدل `<p className="error">` |
| `frontend/src/app/checkout/[slug]/page.tsx` | `<ErrorMsg>` |
| `frontend/src/app/certificates/page.tsx` | `<ErrorMsg>` |
| `frontend/src/app/plans/page.tsx` | `<ErrorMsg>` |
| `frontend/src/app/redeem/page.tsx` | `<ErrorMsg>` |
| `frontend/src/app/survey/[slug]/page.tsx` | `<ErrorMsg>` |
| `frontend/src/app/paths/[slug]/PathDetailClient.tsx` | `<ErrorMsg>` |
| `frontend/src/app/admin/*.tsx` (7 ملفات) | `<ErrorMsg>` |
| `frontend/src/components/studio/LessonEditor.tsx` | `<SuccessMsg>` |
| `frontend/src/app/learn/[slug]/page.tsx` | `<SuccessMsg>` |
| `frontend/src/app/studio/[slug]/page.tsx` | `<SuccessMsg>` |
| `frontend/src/app/leaderboard/page.tsx` | slate-400 → slate-500 (رقم الترتيب) |
| `frontend/tests/a11y.test.tsx` | ملف جديد: اختبارات axe |

---

*آخر تحديث: 2026-06-15 — compliance*
