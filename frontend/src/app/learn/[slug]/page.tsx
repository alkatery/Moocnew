'use client';

// D1: أُضيف استيراد Bookmark ودعم زر toggle العلامة المرجعية
// D4: بحث النصّ + ضوابط المشغّل + التنقّل بين الدروس

import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { Bookmark, Course, CourseProgress, Lesson, LessonContent } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';
import { Gradebook } from '@/components/Gradebook';
import { LessonInteraction } from '@/components/LessonInteraction';
import { TutorWidget } from '@/components/TutorWidget';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';
// D4: مكوّنات الدفعة الجديدة
import { TranscriptSearch } from '@/components/TranscriptSearch';
import { VideoControls } from '@/components/VideoControls';
import { LessonNav } from '@/components/LessonNav';

const TYPE_BADGES: Record<string, string> = {
  video: 'درس فيديو',
  article: 'درس قراءة',
  image: 'درس مصوّر',
  file: 'ملف مرفق',
  live: 'جلسة مباشرة',
};

/**
 * D4: تسطيح أقسام الدورة إلى تسلسل خطّي من الدروس
 * الترتيب: section.position ثم lesson.position (وفق العقد §1.د)
 */
function flattenLessons(course: Course): Lesson[] {
  if (!course.sections) return [];
  return [...course.sections]
    .sort((a, b) => a.position - b.position)
    .flatMap((s) =>
      [...s.lessons].sort((a, b) => a.position - b.position),
    );
}

/**
 * D4: حفظ موضع الفيديو عبر POST /lessons/{lesson}/progress
 * الفشل صامت (لا يكسر التشغيل) — وفق العقد §1.ج
 */
async function saveVideoPosition(lessonId: number, currentTime: number) {
  await api(`/lessons/${lessonId}/progress`, {
    method: 'POST',
    body: { video_position: Math.floor(currentTime) },
  }).catch(() => {});
}

export default function PlayerPage() {
  const { slug } = useParams<{ slug: string }>();
  const [course, setCourse] = useState<Course | null>(null);
  const [active, setActive] = useState<Lesson | null>(null);
  const [playback, setPlayback] = useState<{ kind: string; url: string } | null>(null);
  const [content, setContent] = useState<LessonContent | null>(null);
  const [note, setNote] = useState('');
  const videoRef = useRef<HTMLVideoElement | null>(null);

  // D1: حالات العلامة المرجعية
  const [bookmarkMap, setBookmarkMap] = useState<Map<number, number>>(new Map());
  const [bookmarkBusy, setBookmarkBusy] = useState(false);
  const [bookmarkMsg, setBookmarkMsg] = useState('');
  const [bookmarkErr, setBookmarkErr] = useState('');

  // D4: خريطة التقدّم lessonId → { video_position, completed }
  const [progressMap, setProgressMap] = useState<Map<number, { video_position: number; completed: boolean }>>(new Map());
  // D4: موضع الاستئناف للدرس النشط (null = لا استئناف)
  const [resumeAt, setResumeAt] = useState<number | null>(null);

  // D4: التسلسل المسطّح للدروس + فهرس الدرس النشط
  const flatLessons = course ? flattenLessons(course) : [];
  const activeIndex = active ? flatLessons.findIndex((l) => l.id === active.id) : -1;

  useEffect(() => {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((res) => setCourse(res.data))
      .catch(() => setCourse(null));
  }, [slug]);

  // D4: جلب تقدّم الدورة (video_position لكل درس) مرّة واحدة مع تحميل الدورة
  useEffect(() => {
    api<{ data: CourseProgress }>(`/catalog/courses/${slug}/progress`)
      .then((res) => {
        const map = new Map<number, { video_position: number; completed: boolean }>();
        res.data.lessons.forEach((l) => {
          map.set(l.lesson_id, {
            video_position: l.video_position,
            completed: l.completed,
          });
        });
        setProgressMap(map);
      })
      .catch(() => {
        // الفشل هنا لا يكسر الصفحة — يبدأ بلا استئناف
      });
  }, [slug]);

  // D1: جلب علامات المستخدم مرة واحدة
  useEffect(() => {
    api<{ data: { id: number; lesson: { id: number } }[] }>('/bookmarks')
      .then((r) => {
        const map = new Map<number, number>();
        r.data.forEach((b) => map.set(b.lesson.id, b.id));
        setBookmarkMap(map);
      })
      .catch(() => {
        // الفشل لا يكسر الصفحة
      });
  }, []);

  // D4: حفظ موضع الفيديو عند مغادرة الصفحة (beforeunload)
  useEffect(() => {
    function handleBeforeUnload() {
      if (active?.type === 'video' && playback?.kind === 'signed_url' && videoRef.current) {
        // navigator.sendBeacon أكثر موثوقية في beforeunload — نُعيد استخدام endpoint القائم
        const pos = Math.floor(videoRef.current.currentTime);
        if (pos > 0) {
          void saveVideoPosition(active.id, pos);
        }
      }
    }
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [active, playback]);

  // D4: إعداد حفظ تلقائي كل 20 ثانية أثناء التشغيل (throttled — §1.ج)
  useEffect(() => {
    const vid = videoRef.current;
    if (!vid || !active || active.type !== 'video' || playback?.kind !== 'signed_url') return;

    let lastSaved = 0;
    const INTERVAL = 20; // ثانية

    function onTimeUpdate() {
      const now = vid!.currentTime;
      if (!vid!.paused && now - lastSaved >= INTERVAL) {
        lastSaved = now;
        void saveVideoPosition(active!.id, now);
      }
    }

    function onPause() {
      void saveVideoPosition(active!.id, vid!.currentTime);
    }

    vid.addEventListener('timeupdate', onTimeUpdate);
    vid.addEventListener('pause', onPause);

    return () => {
      vid.removeEventListener('timeupdate', onTimeUpdate);
      vid.removeEventListener('pause', onPause);
    };
  }, [active, playback]);

  /**
   * D4: دالة حفظ الموضع الحالي قبل التنقّل بين الدروس
   */
  const saveCurrentPosition = useCallback(async () => {
    if (active?.type === 'video' && playback?.kind === 'signed_url' && videoRef.current) {
      const pos = videoRef.current.currentTime;
      if (pos > 0) {
        await saveVideoPosition(active.id, pos);
      }
    }
  }, [active, playback]);

  async function open(lesson: Lesson) {
    setActive(lesson);
    setPlayback(null);
    setContent(null);
    setNote('');
    // D4: إعادة ضبط موضع الاستئناف عند فتح درس جديد
    setResumeAt(null);
    try {
      const c = await api<{ data: LessonContent }>(`/lessons/${lesson.id}/content`);
      setContent(c.data);
      if (lesson.type === 'video') {
        const res = await api<{ playback: { kind: string; url: string } }>(`/lessons/${lesson.id}/playback`);
        setPlayback(res.playback);
        // D4: تحديد موضع الاستئناف من progressMap — signed_url فقط (§1.ج)
        if (res.playback.kind === 'signed_url') {
          const saved = progressMap.get(lesson.id);
          if (saved && saved.video_position > 0) {
            setResumeAt(saved.video_position);
          }
        }
      }
    } catch {
      setNote(t('common.error'));
    }
  }

  async function complete(lesson: Lesson) {
    await api(`/lessons/${lesson.id}/progress`, { method: 'POST', body: { completed: true } }).catch(() => {});
    setNote('تم تسجيل إكمال الدرس ✓');
  }

  // D4: التنقّل بين الدروس (سابق/تالٍ) — يحفظ الموضع أولاً
  const handleNavigate = useCallback(async (lesson: Lesson) => {
    await saveCurrentPosition();
    void open(lesson);
  }, [saveCurrentPosition]); // eslint-disable-line react-hooks/exhaustive-deps

  // D1: toggle العلامة المرجعية
  async function toggleBookmark(lesson: Lesson) {
    if (bookmarkBusy) return;
    setBookmarkBusy(true);
    setBookmarkErr('');
    setBookmarkMsg('');
    const existingId = bookmarkMap.get(lesson.id);
    try {
      if (existingId !== undefined) {
        await api(`/bookmarks/${existingId}`, { method: 'DELETE' });
        setBookmarkMap((prev) => {
          const next = new Map(prev);
          next.delete(lesson.id);
          return next;
        });
        setBookmarkMsg(t('lesson.bookmark'));
      } else {
        const res = await api<{ data: Bookmark }>('/bookmarks', {
          method: 'POST',
          body: { lesson_id: lesson.id },
        });
        setBookmarkMap((prev) => new Map(prev).set(lesson.id, res.data.id));
        setBookmarkMsg(t('lesson.bookmarked'));
      }
    } catch {
      setBookmarkErr(t('common.error'));
    } finally {
      setBookmarkBusy(false);
    }
  }

  if (!course) return <p className="label">{t('common.loading')}</p>;

  const isPdf = content?.asset_path?.toLowerCase().endsWith('.pdf') ?? false;

  return (
    <section>
      <PageHeader
        title={course.title}
        crumbs={[{ href: '/learn', label: t('learn.title') }, { label: course.title }]}
        actions={
          <Link className="btn btn-ghost" href={`/community/${slug}`}>
            💬 اسأل المدرّب في مجتمع الدورة
          </Link>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        {/* منطقة المشغّل / المحتوى */}
        <div className="lg:col-span-2">
          {/* D4: شريط التنقّل بين الدروس — يظهر عند اختيار درس، لكل الأنواع */}
          {active && flatLessons.length > 1 && (
            <LessonNav
              lessons={flatLessons}
              activeIndex={activeIndex}
              onNavigate={(lesson) => void handleNavigate(lesson)}
            />
          )}

          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card mt-3">
            {/* منطقة الفيديو (ومرحلة الخمول قبل أي اختيار) */}
            {(!active || active.type === 'video') && (
              <div className="aspect-video w-full bg-slate-900">
                {active && playback?.kind === 'embed' && (
                  <iframe title={active.title} src={playback.url} className="h-full w-full border-0" allowFullScreen />
                )}
                {active && playback?.kind === 'signed_url' && (
                  <video ref={videoRef} controls src={playback.url} className="h-full w-full" />
                )}
                {(!active || !playback) && (
                  <div className="flex h-full w-full flex-col items-center justify-center gap-3 text-slate-400">
                    <svg width="52" height="52" viewBox="0 0 24 24" fill="none" aria-hidden>
                      <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.4" />
                      <path d="M10 9l5 3-5 3z" fill="currentColor" />
                    </svg>
                    <span className="text-sm">{active ? t('common.loading') : 'اختر درساً من قائمة المحتوى لبدء التعلّم'}</span>
                  </div>
                )}
              </div>
            )}

            {/* D4: ضوابط المشغّل — لـ signed_url فقط (§1.ب) */}
            {active?.type === 'video' && playback?.kind === 'signed_url' && (
              <div className="border-b border-slate-100 px-4 py-3">
                <VideoControls videoRef={videoRef} resumeAt={resumeAt} />
              </div>
            )}

            {/* درس قراءة / مباشر */}
            {active && (active.type === 'article' || active.type === 'live') && (
              <div className="min-h-64 whitespace-pre-wrap p-6 leading-relaxed text-slate-700">
                {content?.content ?? t('common.loading')}
              </div>
            )}

            {/* نشاط: تفكير ومشاركة (#3) */}
            {active && active.type === 'activity' && (
              <div className="m-6 rounded-2xl border border-amber-200 bg-amber-50/60 p-6">
                <div className="mb-3 flex items-center gap-2 font-bold text-amber-800">
                  <span aria-hidden>💡</span> نشاط: تفكير ومشاركة
                </div>
                <div className="whitespace-pre-wrap leading-relaxed text-slate-700">
                  {content?.content ?? t('common.loading')}
                </div>
                <p className="mt-4 text-sm text-amber-700">
                  تأمّل في المطلوب أعلاه، ثم شارك إجابتك وناقش زملاءك في{' '}
                  <Link href={`/community/${slug}`} className="font-bold underline">نقاش الدورة</Link>.
                </p>
              </div>
            )}

            {/* درس صورة */}
            {active && active.type === 'image' && (
              <div className="bg-slate-50 p-4 text-center">
                {content?.asset_path
                  // eslint-disable-next-line @next/next/no-img-element
                  ? <img src={content.asset_path} alt={active.title} className="mx-auto max-h-[70vh] rounded-xl" />
                  : <p className="label py-16">{t('common.loading')}</p>}
              </div>
            )}

            {/* درس ملف (PDF…) */}
            {active && active.type === 'file' && (
              <div className="p-4">
                {content?.asset_path ? (
                  <>
                    {isPdf && (
                      <iframe title={active.title} src={content.asset_path} className="mb-3 h-[70vh] w-full rounded-xl border border-slate-200" />
                    )}
                    <a className="btn btn-ghost" href={content.asset_path} target="_blank" rel="noreferrer" download>
                      ⬇ تحميل الملف المرفق
                    </a>
                  </>
                ) : <p className="label py-16 text-center">{t('common.loading')}</p>}
              </div>
            )}

            {active && (
              <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-5">
                <div>
                  <span className="badge mb-1">{TYPE_BADGES[active.type] ?? TYPE_BADGES.article}</span>
                  <h2 className="text-xl">{active.title}</h2>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  {/* رسائل الحالة */}
                  <SuccessMsg msg={bookmarkMsg} />
                  <ErrorMsg msg={bookmarkErr} />
                  <SuccessMsg msg={note} />

                  {/* D1: زر toggle العلامة المرجعية */}
                  {(() => {
                    const isBookmarked = bookmarkMap.has(active.id);
                    return (
                      <button
                        className={`btn btn-ghost ${isBookmarked ? 'text-brand-700' : 'text-slate-600'}`}
                        aria-pressed={isBookmarked}
                        aria-label={isBookmarked ? t('bookmarks.remove') : t('bookmarks.add')}
                        disabled={bookmarkBusy}
                        onClick={() => void toggleBookmark(active)}
                      >
                        <svg
                          width="18"
                          height="18"
                          viewBox="0 0 24 24"
                          fill={isBookmarked ? 'currentColor' : 'none'}
                          stroke="currentColor"
                          strokeWidth="1.8"
                          aria-hidden
                          className="inline-block align-text-bottom"
                        >
                          <path
                            d="M5 3h14a1 1 0 0 1 1 1v17l-8-4-8 4V4a1 1 0 0 1 1-1Z"
                            strokeLinejoin="round"
                          />
                        </svg>
                        <span className="me-1">
                          {isBookmarked ? t('bookmarks.remove') : t('bookmarks.add')}
                        </span>
                      </button>
                    );
                  })()}

                  <button className="btn" onClick={() => void complete(active)}>{t('lesson.complete')}</button>
                </div>
              </div>
            )}
          </div>

          {/* الملاحظات + نقاط التحقّق (الدرس المفتوح) */}
          {active && (
            <LessonInteraction
              lessonId={active.id}
              getTime={() => videoRef.current?.currentTime ?? null}
              seekTo={(s) => { if (videoRef.current) { videoRef.current.currentTime = s; void videoRef.current.play(); } }}
            />
          )}

          {/* D4: لوحة بحث النصّ — تحلّ محلّ <details> القديمة (§1.أ) */}
          {/* تظهر فقط عند درس فيديو بنصّ تفريغ موجود */}
          {active?.type === 'video' && content?.transcript && (
            <TranscriptSearch transcript={content.transcript} />
          )}
        </div>

        {/* الشريط الجانبي — منهج الدورة */}
        <aside>
          <div className="card sticky top-20 max-h-[75vh] overflow-y-auto p-0">
            <div className="border-b border-slate-100 p-4">
              <strong className="text-slate-900">محتوى الدورة</strong>
              <p className="mt-0.5 text-xs text-slate-500">
                {course.sections?.length ?? 0} أقسام · {course.sections?.reduce((n, s) => n + s.lessons.length, 0) ?? 0} درساً
              </p>
            </div>
            {course.sections?.map((s, si) => (
              <div key={s.id} className="border-b border-slate-100 last:border-0">
                <div className="bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-700">
                  القسم {si + 1}: {s.title}
                </div>
                <ul>
                  {s.lessons.map((l) => (
                    <li key={l.id}>
                      <button
                        onClick={() => void open(l)}
                        className={`flex w-full items-center gap-3 px-4 py-3 text-start text-sm transition hover:bg-brand-50 ${
                          active?.id === l.id ? 'bg-brand-50 font-bold text-brand-700' : 'text-slate-600'
                        }`}
                      >
                        <span className={active?.id === l.id ? 'text-brand-600' : 'text-slate-500'}>
                          <LessonTypeIcon type={l.type} />
                        </span>
                        <span className="flex-1">{l.title}</span>
                        {l.is_free_preview && <span className="badge">معاينة</span>}
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>

          <div className="mt-6">
            <Gradebook courseSlug={slug} />
          </div>
        </aside>
      </div>

      <TutorWidget courseSlug={slug} />
    </section>
  );
}
