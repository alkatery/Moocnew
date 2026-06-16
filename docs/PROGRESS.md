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
- ✅ **C2 — مراجعة/تصحيح التسليمات** (موقّع): كشف `student.name` (PDPL) + متحكّم تنزيل ملف محمي (`can('update',course)`، لا path traversal) + `SubmissionReview.tsx` (قائمة→فتح→تصحيح rubric/مباشر+تغذية→حفظ، تأكيد إعادة). الخلفية (index/grade) كانت جاهزة.
- ✅ **C3 — البريد الجماعي والإعلانات** (موقّع): `course_announcements` + صنفا إشعار `ShouldQueue` يرثان `PreferenceAwareNotification` (opt-out مجّاني) · `CourseBroadcaster` إرسال فردي (لا BCC) · POST إعلان (201، throttle 30/1) + GET + POST بريد جماعي (202، throttle 5/1، لا تخزين) · تخويل isStaffFor · تدقيق بالعدد · تبويب «التواصل». 40 اختباراً.
- **✅ المرحلة C (أدوات المعلّم) — مكتملة وموقّعة بالكامل.** متابعة موثّقة غير حاجبة: إضافة اختبار 429 لـ rate limit (السلوك صحيح والـ middleware مطبَّق؛ الواجهة تعالج 429).
## المرحلة 4 — التنفيذ (المرحلة D: إثراء التعلّم) ▶️
- ✅ **D1 — العلامات المرجعية** (موقّع): جدول/موديل/متحكّم على نمط LessonNote · GET/POST(idempotent)/DELETE تحت تخويل LessonAccess · زر toggle (aria-pressed) في مشغّل الدرس + صفحة /bookmarks. عزل بالمستخدم، لا PII.
- ✅ **D2 — المنتدى (تمييز إجابة + متابعة)** (موقّع): `accepted_post_id` (إجابة واحدة بنيوية) + `forum_subscriptions` + `ForumReplyNotification` مُطابور (يحترم opt-out، يتجنّب الكاتب) + توسعة show (can_accept/subscribed) + أزرار a11y (شارة لا لون فقط).
- ✅ **D3 — ملخّصات الإشعارات المجدولة** (موقّع): عمودا digest_frequency/last_digest_at · أمر `DispatchNotificationDigests` (يجمّع notifications بعد آخر ملخّص، لا فارغ، idempotent، daily/weekly بتوقيت المستخدم) · `DigestNotification` مُطابور يحترم opt-out · منتقي تكرار في /notifications.
- ✅ **D4 — البحث في النصّ + ضوابط المشغّل** (موقّع، أمامي بحت): TranscriptSearch (بحث/تظليل `<mark>` آمن + aria-live، لا قفز زمني) · VideoControls (سرعة + استئناف من video_position + «من البداية»، لا تنزيل/جودة بنموذج التشغيل الموقّع) · LessonNav (سابق/تالٍ بحدود). 33 اختباراً.
- **✅ المرحلة D (إثراء التعلّم) — مكتملة وموقّعة بالكامل (D1–D4).**
## المرحلة 4 — التنفيذ (المرحلة E: عمق التأليف) ▶️
- ✅ **E1 — المتطلّبات السابقة** (موقّع): جدول ربط ذاتي + استثناء Domain نقي PrerequisitesNotMet + رفض في EnrollmentService (مجاني+مدفوع، تجاوز للطاقم) + 422 بقائمة المتطلّبات + تأليف (PrerequisitesManager) + show يكشفها. الإكمال=Completed. 11 اختباراً. (امتحان الدخول مؤجَّل P3.)
- ✅ **E2 — جدولة ظهور الأقسام** (موقّع): visible_from على sections + scope موحّد؛ منع تسريب المحتوى المجدول في النقاط الأربع (show/LessonAccess قبل المعاينة/ProgressController/المقام)؛ تجاوز الطاقم؛ SectionScheduler بالاستوديو. 21+11 اختباراً.
- ✅ **E3 — التأليف الجماعي** (موقّع): course_members + توسعة CoursePolicy(update/view)/isStaffFor بـ hasCoAuthor؛ manageMembers للمالك حصراً (منع تصعيد — 10 نواقل محجوبة)؛ CourseMemberController + searchInstructors؛ CourseTeamManager بالاستوديو. 27 اختباراً.
- ✅ **E4 — أنواع أسئلة إضافية** (موقّع): توسعة QuestionType/AnswerGrader بـ dropdown/multi_select/numerical/regex (دون كسر القائم) + عمود config + أمان regex (preg_match، علَمَا i/u، حدود، كاتم أخطاء) + حجب الإجابة عن الطالب. 58 اختباراً.
- ✅ **E5 — مكتبات المحتوى** (موقّع): QuestionImporter (نسخ عميق للحقول السبعة، بلا FK للمصدر — عزل تامّ) + importable/import + عزل ملكية ذرّي (403 بلا نسخ جزئي) + إصلاح ثغرة CourseCloner (config/explanation) + QuestionImportPicker. 25 اختباراً.
- **✅ المرحلة E (عمق التأليف) — مكتملة وموقّعة بالكامل (E1–E5).**

## 🏁 خطّة التنفيذ مكتملة — كل المراحل A→E موقّعة (21 دفعة)
كل دفعة مرّت بالتسلسل الكامل (architect → backend/frontend → integrator → qa → compliance → reviewer مستقلّ) ووُقّعت. آخر حالة خضراء: **pest 590/590 (1898) · front 136/136 · build ناجح · pint نظيف**.

> ملاحظة تشغيل: تعطّل PostgreSQL/Redis عابراً عند إعادة تشغيل الحاوية؛ يُعاد بـ `pg_ctlcluster 16 main start` و`redis-server --daemonize yes` قبل pest.

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
| 2026-06-15 | C2 — مراجعة التسليمات | كشف student.name (PDPL) + SubmissionFileController (تنزيل محمي، 403/404) + SubmissionReview.tsx (تصحيح rubric/مباشر+تغذية+تأكيد إعادة) · الخلفية index/grade جاهزة | pint نظيف · pest 350/350 (1208) · front typecheck/16/build ✅ | ✅ موقّع (مستقلّ) | C3 |
| 2026-06-15 | C3 — بريد جماعي وإعلانات | course_announcements + إشعاران ShouldQueue (PreferenceAware/opt-out) · CourseBroadcaster إرسال فردي · POST إعلان(201)/GET + bulk-email(202) · throttle 30/5 · تدقيق بالعدد · تبويب التواصل | pint نظيف · pest 390/390 (1327) · front typecheck/16/build ✅ | ✅ موقّع (مستقلّ) | المرحلة D |
| 2026-06-15 | D1 — العلامات المرجعية | جدول/موديل/متحكّم (نمط LessonNote) · GET/POST(idempotent 201/200)/DELETE · تخويل LessonAccess · زر toggle aria-pressed + صفحة /bookmarks · عزل بالمستخدم | pint نظيف · pest 403/403 (1398) · front typecheck/30/build ✅ | ✅ موقّع (مستقلّ) | D2 |
| 2026-06-15 | D2 — منتدى (تمييز/متابعة) | accepted_post_id + forum_subscriptions + ForumReplyNotification مُطابور (opt-out، يتجنّب الكاتب) + توسعة show + أزرار aria-pressed وشارة إجابة مقبولة | pint نظيف · pest 427/427 (1514) · front typecheck/53/build ✅ | ✅ موقّع (مستقلّ) | D3 |
| 2026-06-15 | D3 — ملخّصات مجدولة | digest_frequency/last_digest_at + DispatchNotificationDigests (idempotent، لا فارغ، daily/weekly) + DigestNotification مُطابور (opt-out) + منتقي تكرار /notifications | pint نظيف · pest 448/448 (1564) · front typecheck/74/build ✅ | ✅ موقّع (مستقلّ) | D4 |
| 2026-06-15 | D4 — نصّ + ضوابط المشغّل | TranscriptSearch (بحث/تظليل آمن + aria-live، لا قفز) · VideoControls (سرعة + استئناف، لا تنزيل/جودة) · LessonNav (سابق/تالٍ) · أمامي بحت | typecheck نظيف · 107/107 · build ✅ (لا تغيير خلفي) | ✅ موقّع (مستقلّ) | المرحلة E |
| 2026-06-15 | E1 — المتطلّبات السابقة | course_prerequisites + PrerequisitesNotMet (Domain نقي) + رفض EnrollmentService (مجاني+مدفوع، تجاوز طاقم) + 422 بالقائمة + تأليف PrerequisitesManager + show يكشفها | pint نظيف · pest 459/459 (1589) · front typecheck/107/build ✅ | ✅ موقّع (مستقلّ) | E2 |
| 2026-06-15 | E2 — جدولة ظهور الأقسام | visible_from + scope موحّد + فلترة النقاط الأربع (show/LessonAccess قبل المعاينة/Progress 403/المقام) + تجاوز طاقم + SectionScheduler | pint نظيف · pest 480/480 (1643) · front typecheck/118/build ✅ | ✅ موقّع (مستقلّ) | E3 |
| 2026-06-15 | E3 — التأليف الجماعي | course_members + CoursePolicy(update/view)/isStaffFor += hasCoAuthor + manageMembers (مالك حصراً، منع تصعيد) + CourseMemberController/searchInstructors + CourseTeamManager | pint نظيف · pest 507/507 (1699) · front typecheck/118/build ✅ | ✅ موقّع (مستقلّ) | E4 |
| 2026-06-16 | E4 — أنواع أسئلة إضافية | QuestionType/AnswerGrader += dropdown/multi_select/numerical/regex (دون كسر القائم) + config jsonb + regex آمن + حجب الإجابة عن الطالب + تأليف/أداء الأنواع | pint نظيف · pest 565/565 (1830) · front typecheck/120/build ✅ | ✅ موقّع (مستقلّ) | E5 |
| 2026-06-16 | E5 — مكتبات المحتوى | QuestionImporter (نسخ عميق 7 حقول، بلا FK) + importable/import + عزل ملكية ذرّي (403) + إصلاح CourseCloner (config/explanation) + QuestionImportPicker | pint نظيف · pest 590/590 (1898) · front typecheck/136/build ✅ | ✅ موقّع (مستقلّ) | 🏁 الخطّة مكتملة |
