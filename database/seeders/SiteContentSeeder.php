<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contexts\Platform\Infrastructure\Persistence\SiteContent;
use Illuminate\Database\Seeder;

/**
 * Seeds the editable site-content catalogue (branding, logo/images, colours
 * and all marketing copy). Idempotent: only inserts missing keys, never
 * overwrites values an admin has already changed.
 */
final class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $position = 0;

        foreach ($this->defaults() as [$key, $group, $type, $label, $value]) {
            SiteContent::query()->firstOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'type' => $type,
                    'label' => $label,
                    'value' => $value,
                    'position' => $position++,
                ],
            );
        }
    }

    /**
     * @return list<array{0:string,1:string,2:string,3:string,4:string|null}>
     */
    private function defaults(): array
    {
        return [
            // --- Branding ---
            ['brand.name', 'branding', 'text', 'اسم المنصة', 'منصة MOOC'],
            ['brand.tagline', 'branding', 'textarea', 'الوصف التعريفي (تذييل)', 'منصة تعليم جماهيري مفتوح بالعربية: دورات فيديو تفاعلية، اختبارات، شهادات موثّقة، جلسات مباشرة ومجتمع نقاش — للجميع وفي أي وقت.'],
            ['brand.logo', 'branding', 'image', 'الشعار (صورة بديلة عن الافتراضي)', null],
            ['brand.support_email', 'branding', 'text', 'البريد للدعم', 'support@mooc.example'],

            // --- Home hero ---
            ['home.hero_badge', 'home', 'text', 'شارة الهيرو', 'منصة تعليم عربية مفتوحة — تعلّم في أي وقت ومن أي مكان'],
            ['home.hero_title', 'home', 'text', 'عنوان الهيرو', 'تعلّم مهارات المستقبل'],
            ['home.hero_title_accent', 'home', 'text', 'عنوان الهيرو (السطر المميّز)', 'بالعربية… وبشهادات موثّقة'],
            ['home.hero_subtitle', 'home', 'textarea', 'نص الهيرو', 'دورات فيديو تفاعلية مع اختبارات وواجبات وجلسات مباشرة ومجتمع نقاش، تنتهي بشهادة إتمام برمز QR قابل للتحقق.'],
            ['home.hero_image', 'home', 'image', 'صورة/خلفية الهيرو (اختياري)', null],
            ['home.search_placeholder', 'home', 'text', 'نص حقل البحث', 'ماذا تريد أن تتعلّم اليوم؟'],
            ['home.cta_title', 'home', 'text', 'عنوان دعوة التسجيل', 'ابدأ رحلتك التعليمية اليوم'],
            ['home.cta_subtitle', 'home', 'textarea', 'نص دعوة التسجيل', 'أنشئ حسابك مجاناً خلال دقيقة، والتحق بأول دورة من المكتبة المفتوحة.'],

            // --- Open library band ---
            ['home.library_title', 'home', 'text', 'عنوان المكتبة المفتوحة', 'ابدأ مجاناً اليوم — دون أي التزام'],
            ['home.library_body', 'home', 'textarea', 'نص المكتبة المفتوحة', 'مكتبة كاملة من الدورات المجانية بالفيديو والاختبارات، تشمل دروس معاينة مفتوحة في الدورات المدفوعة. سجّل والتحق خلال دقيقة.'],

            // --- About page ---
            ['about.title', 'about', 'text', 'عنوان صفحة عن المنصة', 'نفتح أبواب المعرفة لكل متحدث بالعربية'],
            ['about.intro', 'about', 'textarea', 'مقدمة عن المنصة', 'منصة تعليم جماهيري مفتوح (MOOC) تجمع نخبة المدرّبين مع متعلّمين طموحين: دورات فيديو تفاعلية، تقييمات حقيقية، جلسات مباشرة، وشهادات موثّقة — في تجربة واحدة متكاملة.'],
            ['about.image', 'about', 'image', 'صورة صفحة عن المنصة (اختياري)', null],

            // --- Contact page ---
            ['contact.intro', 'contact', 'textarea', 'مقدمة صفحة التواصل', 'سؤال، اقتراح، أو مشكلة تقنية؟ راسلنا وسنرد عليك خلال يوم عمل واحد.'],
            ['contact.response_time', 'contact', 'text', 'وقت الاستجابة', 'نرد على رسائلك خلال يوم عمل واحد'],

            // --- Footer ---
            ['footer.note', 'footer', 'text', 'سطر حقوق التذييل', 'صُنعت بشغف للتعليم المفتوح بالعربية.'],
        ];
    }
}
