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
 * Arabic demo content for trial/staging boxes: an instructor and a student,
 * categories, published courses with sections and article lessons, a
 * specialised learning path, news posts, an enrollment and a review — so
 * the platform feels alive on first open. Idempotent (guarded by slug),
 * and NOT part of DatabaseSeeder: run explicitly with
 * `php artisan db:seed --class=DemoContentSeeder`.
 */
final class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (Course::query()->where('slug', 'python-basics')->exists()) {
            $this->command?->info('Demo content already seeded — skipping.');

            return;
        }

        // -- People ----------------------------------------------------
        $instructor = $this->user('instructor@mooc.test', 'د. سارة الأحمد', 'Instructor2026', Role::Instructor);
        InstructorProfile::query()->firstOrCreate(
            ['user_id' => $instructor->id],
            ['bio' => 'أستاذة علوم الحاسب، متخصصة في تعليم البرمجة وتطوير الويب بخبرة تتجاوز عشر سنوات في التعليم الإلكتروني.', 'social_links' => []],
        );
        $student = $this->user('student@mooc.test', 'محمد العبدالله', 'Student2026', Role::Student);

        // -- Categories -------------------------------------------------
        $programming = $this->category('البرمجة وعلوم الحاسب', 'programming', 1);
        $business = $this->category('إدارة الأعمال', 'business', 2);
        $languages = $this->category('اللغات والمهارات', 'languages', 3);

        // -- Courses ----------------------------------------------------
        $python = $this->course($instructor, $programming, 'python-basics', 'أساسيات البرمجة بلغة بايثون',
            'تعلّم البرمجة من الصفر بلغة بايثون: المتغيرات والشروط والحلقات والدوال، مع تمارين عملية في كل درس.',
            [
                'مدخل إلى البرمجة' => [
                    ['ما هي البرمجة؟', true],
                    ['تثبيت بايثون وتجهيز بيئة العمل', false],
                ],
                'أساسيات اللغة' => [
                    ['المتغيرات وأنواع البيانات', false],
                    ['الشروط والحلقات التكرارية', false],
                ],
            ]);

        $web = $this->course($instructor, $programming, 'web-development', 'تطوير تطبيقات الويب الحديثة',
            'رحلة متكاملة في تطوير الويب: من HTML وCSS إلى JavaScript وبناء واجهات تفاعلية حديثة.',
            [
                'أساسيات الويب' => [
                    ['كيف يعمل الإنترنت والمتصفح؟', true],
                    ['بناء الصفحات بلغة HTML', false],
                ],
                'التنسيق والتفاعل' => [
                    ['تنسيق الصفحات بـ CSS', false],
                    ['مقدمة في JavaScript', false],
                ],
            ]);

        $pm = $this->course($instructor, $business, 'project-management', 'مهارات إدارة المشاريع',
            'أتقن أساسيات إدارة المشاريع: دورة حياة المشروع، التخطيط والجدولة، وإدارة المخاطر وأصحاب المصلحة.',
            [
                'مدخل إلى إدارة المشاريع' => [
                    ['دورة حياة المشروع', true],
                    ['أصحاب المصلحة وتحليلهم', false],
                ],
                'أدوات المدير الناجح' => [
                    ['التخطيط والجدولة الزمنية', false],
                    ['إدارة المخاطر', false],
                ],
            ]);

        $this->course($instructor, $languages, 'english-beginners', 'اللغة الإنجليزية للمبتدئين',
            'ابدأ تعلّم الإنجليزية بثقة: النطق الصحيح، التحية والتعارف، ومحادثات الحياة اليومية.',
            [
                'الأساسيات' => [
                    ['الحروف وقواعد النطق', true],
                    ['التحية والتعارف', false],
                ],
                'محادثات يومية' => [
                    ['في المطعم والسوق', false],
                    ['السفر والتنقل', false],
                ],
            ]);

        // -- Specialised learning path ---------------------------------
        $path = LearningPath::query()->create([
            'title' => 'مسار مطوّر الويب المتكامل',
            'slug' => 'web-developer-path',
            'summary' => 'ثلاث دورات متدرّجة تنقلك من أساسيات البرمجة إلى تطوير الويب وإدارة مشاريعك التقنية، وتحصل في نهايتها على شهادة المسار.',
            'description' => 'صُمّم هذا المسار التخصصي ليأخذك خطوة بخطوة: تبدأ بأساسيات البرمجة بلغة بايثون، ثم تنتقل إلى تطوير تطبيقات الويب الحديثة، وتختم بمهارات إدارة المشاريع لتدير عملك التقني باحتراف. أكمل المستويات بالترتيب لتحصل على شهادة إتمام المسار.',
            'published_at' => now()->subMinute(),
        ]);
        foreach ([[1, $python], [2, $web], [3, $pm]] as [$level, $course]) {
            LearningPathItem::query()->firstOrCreate(
                ['learning_path_id' => $path->id, 'course_id' => $course->id],
                ['level' => $level, 'position' => 1],
            );
        }

        // -- News --------------------------------------------------------
        NewsPost::query()->create([
            'author_id' => $instructor->id,
            'title' => 'انطلاق المنصة التعليمية رسمياً',
            'slug' => 'platform-launch',
            'excerpt' => 'نعلن اليوم عن الإطلاق الرسمي للمنصة بباقة من الدورات المجانية في البرمجة وإدارة الأعمال واللغات.',
            'body' => "يسعدنا الإعلان عن الإطلاق الرسمي للمنصة التعليمية، ببنية حديثة وتجربة استخدام عربية متكاملة.\n\nتنطلق المنصة بدورات مجانية في البرمجة وإدارة الأعمال واللغات، مع شهادات إتمام موثّقة برمز تحقق، ومسارات تخصصية تجمع الدورات في رحلة تعلم متدرّجة.\n\nسجّل اليوم وابدأ رحلتك التعليمية.",
            'published_at' => now()->subMinutes(30),
        ]);
        NewsPost::query()->create([
            'author_id' => $instructor->id,
            'title' => 'إطلاق المسارات التخصصية وخطط الدراسة',
            'slug' => 'learning-paths-launch',
            'excerpt' => 'ميزتان جديدتان: مسارات تخصصية متدرّجة بشهادة مسار، وخطط دراسة شخصية بتذكيرات مستمرة.',
            'body' => "أطلقنا ميزتين جديدتين لتنظيم رحلتك التعليمية:\n\nالمسارات التخصصية: دورات مرتّبة على مستويات تأخذها بالتسلسل، وتحصل عند إتمامها كاملة على شهادة المسار.\n\nخطط الدراسة الشخصية: اجمع الدورات التي تهمك في خطة واحدة، وحدّد إيقاعك الأسبوعي، وستذكّرك المنصة باستمرار حتى تُنهيها.",
            'published_at' => now()->subMinutes(10),
        ]);

        // -- Student activity --------------------------------------------
        Enrollment::query()->firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $python->id],
            ['status' => EnrollmentStatus::Active, 'progress_percent' => 35, 'enrolled_at' => now()->subDays(5)],
        );
        Enrollment::query()->firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $web->id],
            ['status' => EnrollmentStatus::Active, 'progress_percent' => 10, 'enrolled_at' => now()->subDays(2)],
        );
        CourseReview::query()->firstOrCreate(
            ['course_id' => $python->id, 'user_id' => $student->id],
            ['rating' => 5, 'comment' => 'شرح واضح ومبسّط، والتمارين العملية ساعدتني أفهم كل درس قبل الانتقال للذي يليه.'],
        );

        $this->command?->info('Demo content seeded: 4 courses, 1 path, 2 news posts, demo instructor/student.');
    }

    private function user(string $email, string $name, string $password, Role $role): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->email_verified_at = now();
            $user->save();
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
     * @param  array<string, list<array{0: string, 1: bool}>>  $sections  section title => [[lesson title, is free preview], …]
     */
    private function course(User $instructor, Category $category, string $slug, string $title, string $summary, array $sections): Course
    {
        $course = Course::query()->create([
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'description' => $summary."\n\nهذه الدورة مجانية بالكامل، وتمنحك شهادة إتمام موثّقة عند إنهاء جميع الدروس. يمكنك التعلم بالوتيرة التي تناسبك ومن أي جهاز.",
            'pricing_type' => PricingType::Free,
            'price_minor' => 0,
            'passing_grade' => 0,
            'status' => CourseStatus::Published,
            'published_at' => now()->subMinute(),
        ]);

        $sectionPosition = 0;
        foreach ($sections as $sectionTitle => $lessons) {
            $section = $course->sections()->create(['title' => $sectionTitle, 'position' => ++$sectionPosition]);
            foreach ($lessons as $i => [$lessonTitle, $preview]) {
                $section->lessons()->create([
                    'title' => $lessonTitle,
                    'type' => 'article',
                    'content' => $this->lessonBody($lessonTitle),
                    'position' => $i + 1,
                    'is_free_preview' => $preview,
                ]);
            }
        }

        return $course;
    }

    private function lessonBody(string $title): string
    {
        return "في هذا الدرس «{$title}» نتناول المفهوم خطوة بخطوة بأسلوب مبسّط:\n\n"
            ."أولاً نعرض الفكرة الأساسية وسبب أهميتها في سياق الدورة، مع أمثلة من الواقع تقرّب المعنى.\n\n"
            ."ثم ننتقل إلى التطبيق العملي: جرّب بنفسك الخطوات الموضحة، ولا تتردد في إعادة المحاولة أكثر من مرة — فالتكرار أساس الإتقان.\n\n"
            .'وفي الختام ستجد خلاصة بأهم النقاط، وتمريناً قصيراً يساعدك على ترسيخ ما تعلمته قبل الانتقال إلى الدرس التالي.';
    }
}
