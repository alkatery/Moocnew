# سجل التقدّم (PROGRESS)

> يُحدَّث بعد كل دفعة. الحالة الحيّة للميزات في `docs/feature-matrix.csv`.

## المرحلة 0 — الفهم ✅
- استُخرجت أوراق `docs/openedx-analysis.xlsx` الـ11 برمجياً (openpyxl): 97 ميزة عبر 4 طبقات + 22 مكوّناً + 24 معياراً + أبعاد UX/SEO.
- رُسمت خريطة المنصّة القائمة: 15 سياقاً محدوداً، 66 هجرة، ~62 متحكّم API، 56 ملف اختبار، واجهة Next.js 15.
- قُرئت قيود PRD وADRs والتُزم بها (PDPL، العربية أولاً، الدفع خلف راية، single-tenant).

## المرحلة 1 — مصفوفة الفجوات ✅
- `docs/gap-analysis.md` (سرد + ملخّص تنفيذي أولاً).
- `docs/feature-matrix.csv` (المصدر الوحيد للحقيقة).
- **تصحيح حوكمي:** Horizon/الطوابير موجودة فعلاً (خلافاً لرصد أوّلي).

## المرحلة 2 — الخطة ✅
- `docs/implementation-plan.md`: 5 مراحل (A الامتثال/الهوية → B SEO/الوصول → C أدوات المعلّم → D إثراء التعلّم → E عمق التأليف)، عقد أولاً، بمعايير قبول وتعريف إنجاز.

## المرحلة 3 — فريق الوكلاء ✅
- `.claude/agents/`: architect · backend-dev · frontend-dev · integrator · qa-tester · compliance · reviewer.

## المرحلة 4 — التنفيذ ▶️
- **✅ المرحلة A (الامتثال/الهوية) — مكتملة وموقّعة بالكامل.**
  - ✅ **A1 — تفعيل البريد الإلكتروني** (موقّع من مراجِع مستقلّ).
  - ✅ **A2 — الخدمة الذاتية للحساب** (كلمة المرور/تصدير/حذف PDPL): موقّع. أُغلقت ملاحظة الـ UX (تحديث سياق auth بعد PATCH).
  - ✅ **A3 — إخفاء الهوية الإداري (Retirement)**: موقّع. يعيد استخدام `AnonymizeUser` (لا تعديل عليه — انضباط نطاق).
- **✅ المرحلة B (SEO + WCAG 2.2 AA) — مكتملة وموقّعة بالكامل.**
  - ✅ **B1 — SEO صفحة الدورة** (موقّع): Server Component + `generateMetadata` + JSON‑LD (Course/Offer/BreadcrumbList).
  - ✅ **B2a — SEO المسار + الرئيسية** (موقّع): صفحة المسار → غلاف خادمي + JSON‑LD (EducationalOccupationalProgram/ItemList/BreadcrumbList) + جزيرة تفاعلية (reloadWithAuth بعد الالتحاق)؛ الرئيسية → حقن Organization/WebSite/SearchAction (logo محذوف لغياب أصل عام). أُصلح سقوط محرف في سلسلة SVG زخرفية (مطابقة 1:1).
  - ✅ **B2b — SEO الأخبار + المدرّب** (موقّع): NewsArticle (الخبر) + ProfilePage→Person (المدرّب، sameAs مصفّاة لروابط URL فقط — PDPL) + BreadcrumbList، أغلفة خادمية بلا جزيرة. أُضيف inLanguage:ar للـ ProfilePage.
  - ✅ **B3 — WCAG 2.2 AA** (موقّع): G1 skip link/landmark · G2 `:focus-visible` عام + حلقات تركيز ≥3:1 · G3 ترقية تباين `slate-400→500` شاملة (إبقاء الداكن) · G4 نجاح `emerald-700` · G5 مكوّن `StatusMessage` (alert/status) عبر ~24 ملفاً · G6 tabs/aria-pressed · G7 فحص axe آلي (jest-axe، 16/16).
## المرحلة 4 — التنفيذ (المرحلة C: أدوات المعلّم) ▶️
- ✅ **C1 — Gradebook للمعلّم** (موقّع): `GET /api/v1/assessment/courses/{slug}/gradebook` (تخويل isStaffFor، طالب→403) عبر `GradebookService` دُفعي بلا N+1 + `weightedOverall` مشتركة (DRY)؛ تبويب «درجات الطلاب» بجدول دلالي + فرز + تصدير CSV (UTF-8 BOM) + نمط WAI‑ARIA كامل. PDPL: لا بريد/هاتف، عزل المقررات.
- ⏭️ C2 — مراجعة/تصحيح التسليمات من الواجهة (تجاوز/إعادة درجة).
- ⏭️ C3 — البريد الجماعي والإعلانات (طوابير، تحترم التفضيلات).

> ملاحظات نشر/تأجيل:
> - في الإنتاج يجب أن يكون `APP_URL` عنوان الـ API العام كي يصحّ توقيع رابط التحقّق. `FRONTEND_URL` يضبط صفحة هبوط التحقّق.
> - «الحذف المجدول» (فترة سماح قبل الإخفاء) مؤجَّل؛ المُنفَّذ إخفاء هوية فوري ومؤكَّد — كافٍ لحق المحو PDPL.
> - B1: `NEXT_PUBLIC_SITE_URL` و`NEXT_PUBLIC_API_BASE` يجب ضبطهما في الإنتاج (روابط canonical/OG مطلقة، والجلب الخادمي يحتاج عنوان API يصله الخادم).
> - **حوكمة:** التُقط تعديل مبكّر على `feature-matrix.csv` وسم صفوف SEO «موقّع» قبل حكم المراجِع وأُعيد؛ التوقيع طُبّق فقط بعد توقيع المراجِع الفعلي، وبأمانة على نطاق B1 (المقرر) لا أكثر.

---

### سجل الدفعات
| التاريخ | المرحلة/الدفعة | ما أُنجز | النتائج (pint/pest/front) | توقيع المراجع | التالي |
|---|---|---|---|---|---|
| 2026-06-15 | 0–3 (تأسيس) | تحليل + مصفوفة + خطة + فريق | — (لا كود) | — | اعتُمدت الخطة → A1 |
| 2026-06-15 | A1 — تفعيل البريد | تسجيل بلا توكن + دخول محجوب لغير المُفعّل + تحقّق موقّع (signed+throttle+hash_equals) + إعادة إرسال آمنة من الكشف + واجهات RTL (verify-email/login/register) | pint نظيف · pest 305/305 (1028) · front: typecheck نظيف، 12/12، build ✅ | ✅ موقّع (مستقلّ) | A2 |
| 2026-06-15 | A2 — الخدمة الذاتية للحساب | PATCH ملف · PUT كلمة مرور (إبطال الجلسات الأخرى) · GET تصدير PDPL · DELETE إخفاء هوية (AnonymizeUser) · صفحة /account RTL | pint نظيف · pest 313/313 (1061) · front: typecheck نظيف، 12/12، build ✅ | ✅ موقّع (مستقلّ) | A3 |
| 2026-06-15 | A3 — إخفاء الهوية الإداري | POST /admin/users/{id}/retire (يعيد استخدام AnonymizeUser) · حراسات الذات/الإدارة العليا/الصلاحية · زر «إخفاء الهوية» بالواجهة | pint نظيف · pest 317/317 (1073) · front: typecheck نظيف، 12/12، build ✅ | ✅ موقّع (مستقلّ) | حدّ المرحلة A — انتظار الموافقة |
| 2026-06-15 | B1 — SEO صفحة الدورة | صفحة الدورة → Server Component + generateMetadata (canonical/OG/Twitter) + JSON‑LD (Course/Offer/BreadcrumbList) + CourseDetailClient (جزيرة تفاعلية) · لا تغيير خلفي | front: typecheck نظيف، 12/12، build ✅ (/catalog/[slug] = ƒ Dynamic) · compliance schema.org 27/27 | ✅ موقّع (مستقلّ) | B2a |
| 2026-06-15 | B2a — SEO المسار + الرئيسية | المسار → غلاف خادمي + JSON‑LD (EducationalOccupationalProgram/ItemList/BreadcrumbList) + PathDetailClient (reloadWithAuth)؛ الرئيسية → Organization/WebSite/SearchAction (logo محذوف) + HomeClient · لا تغيير خلفي | front: typecheck نظيف، 12/12، build ✅ (/paths/[slug]=ƒ، /=○) · compliance schema.org OK | ✅ موقّع (مستقلّ) | B2b |
| 2026-06-15 | B2b — SEO الأخبار + المدرّب | news/[slug] → NewsArticle؛ instructors/[id] → ProfilePage(inLanguage:ar)→Person (sameAs روابط فقط PDPL) + BreadcrumbList · أغلفة خادمية · لا تغيير خلفي | front: typecheck نظيف، 12/12، build ✅ (الصفحتان ƒ) · compliance schema.org+PDPL OK | ✅ موقّع (مستقلّ) | B3 |
| 2026-06-15 | B3 — WCAG 2.2 AA | G1 skip link/landmark · G2 focus-visible عام + حلقات ≥3:1 · G3 تباين slate-400→500 شامل · G4 emerald-700 · G5 StatusMessage (alert/status) ~24 ملف · G6 tabs/aria-pressed · G7 jest-axe | front: typecheck نظيف، 16/16 (axe)، build ✅ · لا تغيير خلفي | ✅ موقّع (مستقلّ) | المرحلة C |
| 2026-06-15 | C1 — Gradebook المعلّم | GET /assessment/courses/{slug}/gradebook (isStaffFor، طالب→403) · GradebookService دُفعي بلا N+1 · weightedOverall مشتركة · تبويب جدول+فرز+CSV(BOM)+WAI-ARIA | pint نظيف · pest 339/339 (1155) · front typecheck/16/build ✅ | ✅ موقّع (مستقلّ) | C2 |
