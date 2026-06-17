<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Category;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Content\Infrastructure\Persistence\NewsPost;
use App\Contexts\Engagement\Infrastructure\Persistence\CourseReview;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Identity\Infrastructure\Persistence\InstructorProfile;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPathItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * محتوى تجريبي شرعي (للبيئات التجريبية): شيخ مُدرّس وطالب، تصنيفات
 * (الحديث/الفقه/التفسير)، ثلاث دورات منشورة بأقسام ودروس مقالية ونشاطات
 * تفكير ومشاركة، ومسار شرعي متكامل، وأخبار، والتحاق ومراجعة — لتبدو المنصّة
 * حيّة عند أوّل فتح. متعطّفٌ (idempotent) بمعرّف hadith-intro، ويستبدل أي
 * محتوى تجريبي قديم (دورات البرمجة/الأعمال/اللغات) استبدالاً نظيفاً.
 * ليس جزءاً من DatabaseSeeder: يُشغَّل صراحةً بـ
 * `php artisan db:seed --class=DemoContentSeeder`.
 */
final class DemoContentSeeder extends Seeder
{
    /** معرّفات المحتوى التجريبي القديم — تُزال عند الترقية لاستبدالها بالشرعي. */
    private const LEGACY_COURSE_SLUGS = ['python-basics', 'web-development', 'project-management', 'english-beginners'];

    private const LEGACY_PATH_SLUGS = ['web-developer-path'];

    private const LEGACY_NEWS_SLUGS = ['platform-launch', 'learning-paths-launch'];

    private const LEGACY_CATEGORY_SLUGS = ['programming', 'business', 'languages'];

    public function run(): void
    {
        if (Course::query()->where('slug', 'hadith-intro')->exists()) {
            $this->command?->info('Demo content (Sharia) already seeded — skipping.');

            return;
        }

        $this->removeLegacyDemo();

        // -- People ----------------------------------------------------
        $instructor = $this->user('instructor@mooc.test', 'فضيلة الشيخ د. عبدالرحمن القحطاني', 'Instructor2026', Role::Instructor);
        InstructorProfile::query()->updateOrCreate(
            ['user_id' => $instructor->id],
            ['bio' => 'أستاذ العلوم الشرعية، متخصّص في الحديث وعلومه والفقه وأصوله والتفسير، بخبرة طويلة في التدريس والتعليم الإلكتروني.', 'social_links' => []],
        );
        $student = $this->user('student@mooc.test', 'محمد العبدالله', 'Student2026', Role::Student);

        // -- Categories -------------------------------------------------
        $hadith = $this->category('علوم الحديث', 'hadith', 1);
        $fiqh = $this->category('الفقه', 'fiqh', 2);
        $tafsir = $this->category('التفسير', 'tafsir', 3);

        // -- Courses ----------------------------------------------------
        $hadithCourse = $this->course($instructor, $hadith, 'hadith-intro', 'مدخل إلى علوم الحديث',
            'تعرّف على علم مصطلح الحديث: أقسام الحديث من حيث القبول والردّ، والإسناد ومكانته، والجرح والتعديل، مع نشاطات للتأمّل والمشاركة.',
            [
                'مصطلح الحديث' => [
                    ['تعريف علم الحديث وأهميته', true],
                    ['أقسام الحديث: الصحيح والحسن والضعيف', false],
                    ['نشاط: تأمّل في حديث «إنما الأعمال بالنيات»', false, 'activity'],
                ],
                'السند والرواية' => [
                    ['الإسناد ومكانته في حفظ السنّة', false],
                    ['الجرح والتعديل وعلم الرجال', false],
                ],
            ]);

        $fiqhCourse = $this->course($instructor, $fiqh, 'fiqh-basics', 'أساسيات الفقه الإسلامي',
            'دورة ميسّرة في الفقه العملي: أحكام الطهارة والوضوء، ومواقيت الصلاة وشروطها وصفتها، بأسلوب مبسّط مع نشاط للمناقشة.',
            [
                'الطهارة' => [
                    ['أحكام المياه والطهارة', true],
                    ['الوضوء والغسل والتيمّم', false],
                ],
                'الصلاة' => [
                    ['مواقيت الصلاة وشروط صحّتها', false],
                    ['صفة الصلاة كما وردت', false],
                    ['نشاط: ناقش أثر الخشوع في الصلاة', false, 'activity'],
                ],
            ]);

        $tafsirCourse = $this->course($instructor, $tafsir, 'tafsir-intro', 'مقدمة في علم التفسير',
            'أصول التفسير وعلوم القرآن: نزول القرآن وجمعه، أسباب النزول، طرق التفسير ومصادره، والفرق بين التفسير بالمأثور وبالرأي، مع نشاط للتدبّر.',
            [
                'علوم القرآن' => [
                    ['نزول القرآن وجمعه وترتيبه', true],
                    ['أسباب النزول وأثرها في الفهم', false],
                ],
                'أصول التفسير' => [
                    ['طرق التفسير ومصادره', false],
                    ['التفسير بالمأثور والتفسير بالرأي', false],
                    ['نشاط: تدبّر في معاني سورة الفاتحة', false, 'activity'],
                ],
            ]);

        // -- Specialised learning path ---------------------------------
        $path = LearningPath::query()->create([
            'title' => 'المسار الشرعي المتكامل',
            'slug' => 'sharia-path',
            'summary' => 'ثلاث دورات متدرّجة في العلوم الشرعية: التفسير ثم الحديث ثم الفقه، تأخذها بالتسلسل وتحصل في نهايتها على شهادة المسار.',
            'description' => 'صُمّم هذا المسار ليأخذك خطوة بخطوة في طلب العلم الشرعي: تبدأ بمقدمة في علم التفسير وعلوم القرآن، ثم تنتقل إلى مدخل علوم الحديث، وتختم بأساسيات الفقه العملي. أكمل المستويات بالترتيب لتحصل على شهادة إتمام المسار.',
            'published_at' => now()->subMinute(),
        ]);
        foreach ([[1, $tafsirCourse], [2, $hadithCourse], [3, $fiqhCourse]] as [$level, $course]) {
            LearningPathItem::query()->firstOrCreate(
                ['learning_path_id' => $path->id, 'course_id' => $course->id],
                ['level' => $level, 'position' => 1],
            );
        }

        // -- News --------------------------------------------------------
        NewsPost::query()->create([
            'author_id' => $instructor->id,
            'title' => 'انطلاق منصّة العلوم الشرعية رسمياً',
            'slug' => 'sharia-platform-launch',
            'excerpt' => 'نعلن اليوم عن الإطلاق الرسمي للمنصّة بباقة من الدورات الشرعية المجانية في الحديث والفقه والتفسير.',
            'body' => "يسعدنا الإعلان عن الإطلاق الرسمي للمنصّة التعليمية الشرعية، ببنية حديثة وتجربة استخدام عربية متكاملة.\n\nتنطلق المنصّة بدورات مجانية في علوم الحديث والفقه والتفسير، مع شهادات إتمام موثّقة برمز تحقّق، ومسار شرعي متكامل يجمع الدورات في رحلة تعلّم متدرّجة.\n\nسجّل اليوم وابدأ رحلتك في طلب العلم.",
            'published_at' => now()->subMinutes(30),
        ]);
        NewsPost::query()->create([
            'author_id' => $instructor->id,
            'title' => 'إطلاق المسار الشرعي وخطط الدراسة',
            'slug' => 'sharia-path-launch',
            'excerpt' => 'ميزتان جديدتان: مسار شرعي متدرّج بشهادة مسار، وخطط دراسة شخصية بتذكيرات مستمرة.',
            'body' => "أطلقنا ميزتين جديدتين لتنظيم رحلتك في طلب العلم:\n\nالمسار الشرعي: دورات مرتّبة على مستويات تأخذها بالتسلسل (التفسير ثم الحديث ثم الفقه)، وتحصل عند إتمامها كاملة على شهادة المسار.\n\nخطط الدراسة الشخصية: اجمع الدورات التي تهمّك في خطة واحدة، وحدّد إيقاعك الأسبوعي، وستذكّرك المنصّة باستمرار حتى تُنهيها.",
            'published_at' => now()->subMinutes(10),
        ]);

        // -- Student activity --------------------------------------------
        Enrollment::query()->firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $hadithCourse->id],
            ['status' => EnrollmentStatus::Active, 'progress_percent' => 35, 'enrolled_at' => now()->subDays(5)],
        );
        Enrollment::query()->firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $tafsirCourse->id],
            ['status' => EnrollmentStatus::Active, 'progress_percent' => 10, 'enrolled_at' => now()->subDays(2)],
        );
        CourseReview::query()->firstOrCreate(
            ['course_id' => $hadithCourse->id, 'user_id' => $student->id],
            ['rating' => 5, 'comment' => 'شرح واضح ومبسّط، والنشاطات ساعدتني على التأمّل وترسيخ ما تعلّمته قبل الانتقال للدرس التالي.'],
        );

        $this->command?->info('Demo content seeded: 3 Sharia courses (الحديث/الفقه/التفسير), 1 path, 2 news posts, demo instructor/student.');
    }

    /**
     * إزالة المحتوى التجريبي القديم (البرمجة/الأعمال/اللغات) استبدالاً نظيفاً.
     * مُستهدَفة بمعرّفات ثابتة فقط، فلا تمسّ أي محتوى حقيقي أضافه المستخدم.
     */
    private function removeLegacyDemo(): void
    {
        LearningPath::query()->whereIn('slug', self::LEGACY_PATH_SLUGS)->each(fn (LearningPath $p) => $p->delete());
        NewsPost::query()->whereIn('slug', self::LEGACY_NEWS_SLUGS)->delete();
        // حذف الدورات يحذف أقسامها ودروسها والتحاقاتها ومراجعاتها (cascade).
        Course::query()->whereIn('slug', self::LEGACY_COURSE_SLUGS)->each(fn (Course $c) => $c->delete());
        // التصنيفات القديمة تُحذف فقط إن أصبحت فارغة (لا دورات تحتها).
        Category::query()->whereIn('slug', self::LEGACY_CATEGORY_SLUGS)
            ->whereDoesntHave('courses')
            ->delete();
    }

    private function user(string $email, string $name, string $password, Role $role): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->email_verified_at = now();
            $user->save();
        } else {
            // ترقية الاسم لمحتوى الشرعي إن كان حساباً تجريبياً قديماً.
            $user->forceFill(['name' => $name])->save();
        }

        if (! $user->hasRole($role->value)) {
            $user->assignRole($role->value);
        }

        return $user;
    }

    private function category(string $name, string $slug, int $position): Category
    {
        return Category::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'position' => $position]);
    }

    /**
     * @param  array<string, list<array{0: string, 1: bool, 2?: string}>>  $sections  section title => [[lesson title, is free preview, type?], …]
     */
    private function course(User $instructor, Category $category, string $slug, string $title, string $summary, array $sections): Course
    {
        $course = Course::query()->create([
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'description' => $summary."\n\nهذه الدورة مجانية بالكامل، وتمنحك شهادة إتمام موثّقة عند إنهاء جميع الدروس. يمكنك التعلّم بالوتيرة التي تناسبك ومن أي جهاز.",
            'pricing_type' => PricingType::Free,
            'price_minor' => 0,
            'passing_grade' => 0,
            'status' => CourseStatus::Published,
            'published_at' => now()->subMinute(),
        ]);

        $sectionPosition = 0;
        foreach ($sections as $sectionTitle => $lessons) {
            $section = $course->sections()->create(['title' => $sectionTitle, 'position' => ++$sectionPosition]);
            foreach ($lessons as $i => $lesson) {
                [$lessonTitle, $preview] = $lesson;
                $type = $lesson[2] ?? 'article';
                $section->lessons()->create([
                    'title' => $lessonTitle,
                    'type' => $type,
                    'content' => $type === 'activity' ? $this->activityPrompt($lessonTitle) : $this->lessonBody($lessonTitle),
                    'position' => $i + 1,
                    'is_free_preview' => $preview,
                ]);
            }
        }

        return $course;
    }

    private function lessonBody(string $title): string
    {
        return "في هذا الدرس «{$title}» نتناول الموضوع خطوة بخطوة بأسلوب مبسّط:\n\n"
            ."أولاً نعرض المسألة وأهميتها في سياق الدورة، مع أدلّتها وأمثلة تقرّب المعنى.\n\n"
            ."ثم ننتقل إلى التطبيق والفهم: راجِع ما سبق، ولا تتردّد في إعادة القراءة أكثر من مرة — فالتكرار أساس الرسوخ.\n\n"
            .'وفي الختام ستجد خلاصة بأهمّ النقاط، وسؤالاً قصيراً يساعدك على ترسيخ ما تعلّمته قبل الانتقال إلى الدرس التالي.';
    }

    private function activityPrompt(string $title): string
    {
        return "نشاط «{$title}»:\n\n"
            ."تأمّل في المسألة المطروحة، ثم اكتب ما توصّلت إليه وشاركه مع زملائك في «نقاش الدورة».\n\n"
            .'حاول أن تستدلّ لما تقول بدليل من الكتاب أو السنّة أو كلام أهل العلم، ثم اقرأ مشاركات زملائك وعلّق عليها بأدب.';
    }
}
