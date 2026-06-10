'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { Course, Lesson } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';

export default function PlayerPage() {
  const { slug } = useParams<{ slug: string }>();
  const [course, setCourse] = useState<Course | null>(null);
  const [active, setActive] = useState<Lesson | null>(null);
  const [playback, setPlayback] = useState<{ kind: string; url: string } | null>(null);
  const [note, setNote] = useState('');

  useEffect(() => {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((res) => setCourse(res.data))
      .catch(() => setCourse(null));
  }, [slug]);

  async function open(lesson: Lesson) {
    setActive(lesson);
    setPlayback(null);
    setNote('');
    try {
      const res = await api<{ playback: { kind: string; url: string } }>(`/lessons/${lesson.id}/playback`);
      setPlayback(res.playback);
    } catch {
      setNote(t('common.error'));
    }
  }

  async function complete(lesson: Lesson) {
    await api(`/lessons/${lesson.id}/progress`, { method: 'POST', body: { completed: true } }).catch(() => {});
    setNote('تم تسجيل إكمال الدرس ✓');
  }

  if (!course) return <p className="label">{t('common.loading')}</p>;

  return (
    <section>
      <PageHeader
        title={course.title}
        crumbs={[{ href: '/learn', label: t('learn.title') }, { label: course.title }]}
      />

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Player area */}
        <div className="lg:col-span-2">
          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card">
            <div className="aspect-video w-full bg-slate-900">
              {active && playback?.kind === 'embed' && (
                <iframe title={active.title} src={playback.url} className="h-full w-full border-0" allowFullScreen />
              )}
              {active && playback?.kind === 'signed_url' && (
                <video controls src={playback.url} className="h-full w-full" />
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

            {active && (
              <div className="flex flex-wrap items-center justify-between gap-3 p-5">
                <div>
                  <span className="badge mb-1">{active.type === 'video' ? 'درس فيديو' : active.type === 'live' ? 'جلسة مباشرة' : 'درس قراءة'}</span>
                  <h2 className="text-xl">{active.title}</h2>
                </div>
                <div className="flex items-center gap-3">
                  {note && <span className="success">{note}</span>}
                  <button className="btn" onClick={() => void complete(active)}>{t('lesson.complete')}</button>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Curriculum sidebar */}
        <aside>
          <div className="card sticky top-20 max-h-[75vh] overflow-y-auto p-0">
            <div className="border-b border-slate-100 p-4">
              <strong className="text-slate-900">محتوى الدورة</strong>
              <p className="mt-0.5 text-xs text-slate-400">
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
                        <span className={active?.id === l.id ? 'text-brand-600' : 'text-slate-400'}>
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
        </aside>
      </div>
    </section>
  );
}
