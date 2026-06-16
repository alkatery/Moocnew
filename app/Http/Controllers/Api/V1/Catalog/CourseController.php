<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Category;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Shared\Application\ImageUploader;
use App\Contexts\Shared\Application\SlugGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCourseRequest;
use App\Http\Requests\Catalog\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CourseController extends Controller
{
    /**
     * Courses authored by the current instructor, in any status — powers the
     * instructor studio. Staff (courses.review) see every course so they can
     * manage work they created on behalf of instructors.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $courses = Course::query()
            ->unless(
                $request->user()->can(Permission::ReviewCourses->value),
                // E3: يشمل مقررات المستخدم كمالك ومقررات هو co-author فيها.
                fn ($q) => $q->where(fn ($w) => $w
                    ->where('instructor_id', $request->user()->getKey())
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', $request->user()->getKey()))),
            )
            ->with(['category', 'instructor'])
            ->latest()
            ->paginate(20);

        return CourseResource::collection($courses);
    }

    /**
     * Public catalogue listing: only published courses, with optional
     * full-text search (Scout/Meilisearch) and category/pricing filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 50);
        $term = trim((string) $request->query('q', ''));

        $categoryId = $this->resolveCategoryId($request->query('category'));
        $pricing = $request->query('pricing'); // free|paid|null

        $sort = (string) $request->query('sort', 'newest');

        if ($term !== '') {
            $paginator = Course::search($term)
                ->query(fn (Builder $query) => $this->applySort($this->applyCatalogueFilters($query, $categoryId, $pricing), $sort))
                ->paginate($perPage);
        } else {
            $paginator = $this->applySort($this->applyCatalogueFilters(Course::query(), $categoryId, $pricing), $sort)
                ->paginate($perPage);
        }

        return CourseResource::collection($paginator);
    }

    public function show(Request $request, Course $course, CourseAccess $courseAccess): CourseResource
    {
        abort_unless($request->user()?->can('view', $course) ?? $course->status === CourseStatus::Published, 404);

        // E2: تحديد هوية المُشاهِد — الطاقم يرى كل الأقسام، الطالب/الزائر يرى الظاهر فقط.
        $user = $request->user();
        $isStaff = $user !== null && $courseAccess->isStaffFor($user, $course);

        $course->load([
            'category',
            'instructor',
            // E2: فلترة الأقسام بـ scopeVisibleTo في eager-load واحد (منع N+1).
            // الطاقم يرى الكل؛ الطالب/الزائر يرى visible_from IS NULL OR visible_from <= now().
            'sections' => fn ($q) => $q->visibleTo($isStaff)->with('lessons'),
            // E1: المتطلّبات السابقة المنشورة فقط (يقرأها زر الالتحاق في الواجهة).
            'prerequisites' => fn ($q) => $q
                ->where('status', CourseStatus::Published->value)
                ->select(['courses.id', 'courses.title', 'courses.slug']),
        ])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new CourseResource($course);
    }

    public function store(StoreCourseRequest $request, SlugGenerator $slugs): JsonResponse
    {
        $pricingType = PricingType::from($request->validated('pricing_type'));

        $course = Course::query()->create([
            'instructor_id' => $this->resolveInstructorId($request, $request->validated('instructor_id')),
            'category_id' => $request->validated('category_id'),
            'title' => $request->validated('title'),
            'slug' => $slugs->forTitle($request->validated('title'), 'courses'),
            'summary' => $request->validated('summary'),
            'description' => $request->validated('description'),
            'status' => CourseStatus::Draft,
            'pricing_type' => $pricingType,
            'price_minor' => $pricingType === PricingType::Free ? 0 : (int) $request->validated('price_minor', 0),
            'passing_grade' => (int) $request->validated('passing_grade', 0),
        ]);

        return (new CourseResource($course))->response()->setStatusCode(201);
    }

    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $data = $request->safe()->only([
            'title',
            'category_id',
            'summary',
            'description',
            'pricing_type',
            'price_minor',
            'passing_grade',
        ]);

        // Staff may reassign the course to another instructor.
        if ($request->filled('instructor_id')) {
            $data['instructor_id'] = $this->resolveInstructorId($request, (int) $request->validated('instructor_id'));
        }

        // A free course always carries a zero price.
        if (($data['pricing_type'] ?? $course->pricing_type->value) === PricingType::Free->value) {
            $data['price_minor'] = 0;
        }

        $course->update($data);

        return new CourseResource($course->fresh(['category', 'instructor']));
    }

    /**
     * Staff (courses.review) may author on behalf of any instructor-capable
     * user; everyone else owns what they create. Assigning to a user who
     * cannot manage courses is rejected so courses never become orphaned.
     */
    private function resolveInstructorId(Request $request, ?int $requested): int
    {
        $actor = $request->user();

        if ($requested === null || $requested === $actor->getKey() || ! $actor->can(Permission::ReviewCourses->value)) {
            return $actor->getKey();
        }

        $target = User::query()->findOrFail($requested);

        abort_unless(
            $target->hasRole(Role::Instructor->value) || $target->can(Permission::ManageCourses->value),
            422,
            'المستخدم المحدد ليس مدرّباً.',
        );

        return $target->getKey();
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()?->can('delete', $course) ?? false, 403);

        $course->delete();

        return response()->json(status: 204);
    }

    /**
     * Upload a real cover image for the course (owner/staff only).
     */
    public function uploadCover(Request $request, Course $course, ImageUploader $uploader): JsonResponse
    {
        abort_unless($request->user()?->can('update', $course) ?? false, 403);

        $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);

        $url = $uploader->store($request->file('image'), 'covers', $course->cover_image);
        $course->update(['cover_image' => $url]);

        return response()->json(['data' => ['cover_image' => $url]]);
    }

    private function applyCatalogueFilters(Builder $query, ?int $categoryId, ?string $pricing): Builder
    {
        return $query
            ->where('status', CourseStatus::Published->value)
            ->with(['category', 'instructor'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($categoryId !== null, fn (Builder $q) => $q->where('category_id', $categoryId))
            ->when($pricing === 'free', fn (Builder $q) => $q->where('pricing_type', PricingType::Free->value))
            ->when($pricing === 'paid', fn (Builder $q) => $q->where('pricing_type', '!=', PricingType::Free->value));
    }

    /**
     * Order the catalogue: newest, top-rated, or most popular (by enrollments).
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'top_rated' => $query->orderByRaw('reviews_avg_rating DESC NULLS LAST')->latest('published_at'),
            'popular' => $query->withCount('enrollments')->orderByDesc('enrollments_count'),
            default => $query->latest('published_at'),
        };
    }

    private function resolveCategoryId(mixed $slug): ?int
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Category::query()->where('slug', $slug)->value('id');
    }
}
