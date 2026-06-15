import Link from 'next/link';
import type { Course } from '@/lib/types';
import { formatMinor } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { Stars } from '@/components/Stars';

const BANNERS = [
  'from-brand-500 to-brand-700',
  'from-sky-500 to-indigo-700',
  'from-emerald-500 to-teal-700',
  'from-amber-500 to-orange-600',
  'from-rose-500 to-pink-700',
  'from-violet-500 to-purple-700',
];

export function CourseCard({ course, index = 0 }: { course: Course; index?: number }) {
  return (
    <Link
      href={`/catalog/${course.slug}`}
      className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card transition hover:-translate-y-0.5 hover:shadow-lg"
    >
      {course.cover_image ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={course.cover_image} alt={course.title} className="h-32 w-full object-cover" />
      ) : (
        <div className={`relative h-28 bg-gradient-to-bl ${BANNERS[index % BANNERS.length]}`}>
          <svg className="absolute -bottom-4 end-4 opacity-25" width="72" height="72" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M12 3 2 8l10 5 10-5-10-5Z" fill="#fff" />
            <path d="M6 12.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-3.5l-6 3-6-3Z" fill="#fff" />
          </svg>
          {course.category?.name && (
            <span className="absolute bottom-2.5 start-3 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur">
              {course.category.name}
            </span>
          )}
        </div>
      )}
      <div className="flex flex-1 flex-col p-4">
        <div className="mb-1 flex items-center justify-between gap-2">
          <strong className="line-clamp-1 text-slate-900">{course.title}</strong>
          <span className="badge shrink-0">
            {course.pricing_type === 'free' ? t('course.free') : formatMinor(course.price_minor)}
          </span>
        </div>
        <p className="line-clamp-2 text-sm text-slate-500">{course.summary}</p>
        <div className="mt-auto flex items-center justify-between pt-3">
          {course.instructor?.name && (
            /* G3: slate-400 (2.56:1) → slate-500 (3.95:1) على خلفية بيضاء */
            <span className="text-xs font-medium text-slate-500">{course.instructor.name}</span>
          )}
          {(course.reviews_count ?? 0) > 0 && course.rating != null && (
            <span className="flex items-center gap-1">
              <Stars value={course.rating} size={13} />
              {/* G3: slate-400 → slate-500 */}
              <span className="text-xs text-slate-500">({course.reviews_count})</span>
            </span>
          )}
        </div>
      </div>
    </Link>
  );
}
