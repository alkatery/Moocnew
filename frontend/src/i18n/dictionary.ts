// Minimal i18n with Arabic as the default (RTL); English fallback (PRD §7).
export type Locale = 'ar' | 'en';

export const defaultLocale: Locale = 'ar';

const dictionaries = {
  ar: {
    'app.name': 'منصة MOOC',
    'nav.catalog': 'الدورات',
    'nav.myLearning': 'تعلّمي',
    'nav.login': 'تسجيل الدخول',
    'nav.register': 'إنشاء حساب',
    'nav.logout': 'خروج',
    'catalog.title': 'الدورات المتاحة',
    'catalog.search': 'ابحث عن دورة…',
    'catalog.empty': 'لا توجد دورات.',
    'course.enroll': 'التحاق',
    'course.enrolled': 'ملتحق',
    'course.free': 'مجاني',
    'auth.email': 'البريد الإلكتروني',
    'auth.password': 'كلمة المرور',
    'auth.name': 'الاسم',
    'auth.submitLogin': 'دخول',
    'auth.submitRegister': 'تسجيل',
    'auth.consent': 'أوافق على سياسة الخصوصية ومعالجة البيانات',
    'learn.title': 'دوراتي',
    'learn.progress': 'الإنجاز',
    'lesson.complete': 'إكمال الدرس',
    'lesson.watch': 'تشغيل',
    'common.loading': 'جارٍ التحميل…',
    'common.error': 'حدث خطأ.',
  },
  en: {
    'app.name': 'MOOC Platform',
    'nav.catalog': 'Courses',
    'nav.myLearning': 'My Learning',
    'nav.login': 'Sign in',
    'nav.register': 'Sign up',
    'nav.logout': 'Sign out',
    'catalog.title': 'Available courses',
    'catalog.search': 'Search courses…',
    'catalog.empty': 'No courses.',
    'course.enroll': 'Enroll',
    'course.enrolled': 'Enrolled',
    'course.free': 'Free',
    'auth.email': 'Email',
    'auth.password': 'Password',
    'auth.name': 'Name',
    'auth.submitLogin': 'Sign in',
    'auth.submitRegister': 'Register',
    'auth.consent': 'I agree to the privacy & data-processing policy',
    'learn.title': 'My courses',
    'learn.progress': 'Progress',
    'lesson.complete': 'Complete lesson',
    'lesson.watch': 'Play',
    'common.loading': 'Loading…',
    'common.error': 'Something went wrong.',
  },
} as const;

export type TranslationKey = keyof (typeof dictionaries)['ar'];

export function t(key: TranslationKey, locale: Locale = defaultLocale): string {
  return dictionaries[locale][key] ?? dictionaries.en[key] ?? key;
}
