'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { API_BASE, api, getToken } from '@/lib/api';
import type { Course, LessonKind, Section } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';
import { HelpGuide } from '@/components/HelpGuide';
import { LessonEditor } from '@/components/studio/LessonEditor';
import { AssessmentsPanel } from '@/components/studio/AssessmentsPanel';
import { InstructorGradebook } from '@/components/studio/InstructorGradebook';
import { CommunicationsPanel } from '@/components/studio/CommunicationsPanel';
import { badgeTone, statusLabel } from '@/lib/labels';
import { SuccessMsg } from '@/components/StatusMessage';

const LESSON_KINDS: { value: LessonKind; label: string }[] = [
  { value: 'article', label: 'مقال' },
  { value: 'video', label: 'فيديو' },
  { value: 'image', label: 'صورة' },
  { value: 'file', label: 'ملف PDF' },
  { value: 'live', label: 'جلسة مباشرة' },
];

export default function ManageCoursePage() {
  const { slug } = useParams<{ slug: string }>();
  const [course, setCourse] = useState<Course | null>(null);
  const [tab, setTab] = useState<'curriculum' | 'assessments' | 'gradebook' | 'communications'>('curriculum');
  const [sectionTitle, setSectionTitle] = useState('');
  const [passingGrade, setPassingGrade] = useState('0');
  const [note, setNote] = useState('');
  const [cover, setCover] = useState<string | null>(null);
  const [openLesson, setOpenLesson] = useState<number | null>(null);
  const [loadError, setLoadError] = useState(false);

  const load = useCallback(() => {
    // Authoring view: fetch WITH auth so the owner can load their own draft
    // (drafts 404 for anonymous requests).
    api<{ data: Course }>(`/catalog/courses/${slug}`)
      .then((r) => { setCourse(r.data); setPassingGrade(String(r.data.passing_grade ?? 0)); setCover(r.data.cover_image ?? null); setLoadError(false); })
      .catch(() => setLoadError(true));
  }, [slug]);
  useEffect(load, [load]);

  const sections: Section[] = course?.sections ?? [];

  async function uploadCover(file: File) {
    setNote('');
    try {
      const form = new FormData();
      form.append('image', file);
      const res = await fetch(`${API_BASE}/catalog/courses/${slug}/cover`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'application/json' },
        body: form,
      });
      if (!res.ok) throw new Error('upload failed');
      const json = await res.json();
      setCover(json.data.cover_image as string);
      setNote(t('common.save') + ' ✓');
    } catch { setNote(t('common.error')); }
  }

  async function saveSettings(e: React.FormEvent) {
    e.preventDefault();
    try {
      await api(`/catalog/courses/${slug}`, {
        method: 'PATCH',
        body: { passing_grade: Math.max(0, Math.min(100, parseInt(passingGrade || '0', 10))) },
      });
      setNote(t('common.save') + ' ✓');
      load();
    } catch { setNote(t('common.error')); }
  }

  async function addSection(e: React.FormEvent) {
    e.preventDefault();
    await api(`/catalog/courses/${slug}/sections`, {
      method: 'POST', body: { title: sectionTitle, position: sections.length + 1 },
    }).catch(() => setNote(t('common.error')));
    setSectionTitle('');
    load();
  }

  async function renameSection(s: Section) {
    const title = window.prompt('اسم القسم الجديد:', s.title);
    if (!title || title === s.title) return;
    await api(`/catalog/sections/${s.id}`, { method: 'PATCH', body: { title } }).catch(() => setNote(t('common.error')));
    load();
  }

  async function deleteSection(s: Section) {
    if (!window.confirm(`حذف القسم «${s.title}» وكل دروسه؟`)) return;
    await api(`/catalog/sections/${s.id}`, { method: 'DELETE' }).catch(() => setNote(t('common.error')));
    load();
  }

  async function moveSection(index: number, dir: -1 | 1) {
    const ids = sections.map((s) => s.id);
    const j = index + dir;
    if (j < 0 || j >= ids.length) return;
    [ids[index], ids[j]] = [ids[j], ids[index]];
    await api(`/catalog/courses/${slug}/sections/order`, { method: 'PUT', body: { ids } }).catch(() => setNote(t('common.error')));
    load();
  }

  async function moveLesson(s: Section, index: number, dir: -1 | 1) {
    const ids = s.lessons.map((l) => l.id);
    const j = index + dir;
    if (j < 0 || j >= ids.length) return;
    [ids[index], ids[j]] = [ids[j], ids[index]];
    await api(`/catalog/sections/${s.id}/lessons/order`, { method: 'PUT', body: { ids } }).catch(() => setNote(t('common.error')));
    load();
  }

  async function submit() {
    try {
      await api(`/catalog/courses/${slug}/submit`, { method: 'POST' });
      setNote(t('studio.submit') + ' ✓');
      load();
    } catch (e) { setNote(e instanceof Error ? e.message : t('common.error')); }
  }

  if (loadError) {
    return (
      <section>
        <PageHeader title="تعذّر فتح الدورة" crumbs={[{ href: '/studio', label: t('nav.studio') }]} />
        <div className="card text-slate-600">
          لم نتمكّن من تحميل هذه الدورة. تأكّد أنك مالكها أو من الإدارة، ثم
          <button className="btn btn-ghost ms-2" onClick={load}>أعد المحاولة</button>
        </div>
      </section>
    );
  }
  if (!course) return <p className="label">{t('common.loading')}</p>;

  return (
    <section>
      <PageHeader
        title={course.title}
        badge={<span className={`badge ${badgeTone(course.status)}`}>{statusLabel(course.status)}</span>}
        crumbs={[{ href: '/studio', label: t('nav.studio') }, { label: course.title }]}
        actions={
          <>
            {/* G5: role="status" عبر SuccessMsg */}
            <SuccessMsg msg={note} />
            {course.status === 'draft' || course.status === 'rejected' ? (
              <button className="btn" onClick={() => void submit()}>{t('studio.submit')}</button>
            ) : course.status === 'pending_review' ? (
              <span className="badge">قيد المراجعة لدى الإدارة</span>
            ) : (
              <span className="badge bg-emerald-50 text-emerald-700">منشورة ومتاحة للطلاب</span>
            )}
          </>
        }
      />

      <HelpGuide
        title="دليل بناء هذه الدورة"
        intro="ابنِ المنهج من تبويب «المنهج»، وأنشئ بنك الأسئلة والاختبارات والواجبات من تبويب «التقييمات والدرجات»، ثم أرسل الدورة للمراجعة."
        steps={[
          { title: 'المنهج', body: 'أضف الأقسام ثم الدروس داخلها. اضغط على أي درس لفتح محرره الكامل: نوع المادة (مقال/فيديو/صورة/PDF/جلسة)، المحتوى، رفع الملفات، التفريغ النصي، والمعاينة المجانية. رتّب بالأسهم ▲▼.' },
          { title: 'التقييمات والدرجات', body: 'أنشئ أسئلة في البنك (اختيار من متعدد، صح/خطأ، إجابة قصيرة)، ثم جمّعها في اختبارات بمدة ومحاولات ووزن، وأضف واجبات تصححها يدوياً. وزن كل تقييم يحدد أثره في الدرجة النهائية.' },
          { title: 'درجة النجاح', body: 'من «إعدادات الدورة» حدد الدرجة المطلوبة للشهادة — تُحسب من متوسط الاختبارات والواجبات الموزون.' },
          { title: 'النشر', body: 'اضغط «إرسال للمراجعة» وستنشرها الإدارة بعد الاعتماد.' },
        ]}
      />

      {/* G6: role="tablist"/"tab"/aria-selected — G3: slate-400→slate-500 للتبويب غير النشط */}
      {/* C1: أضيف تبويب ثالث «درجات الطلاب» بنفس نمط a11y القائم */}
      {/* C3: أضيف تبويب رابع «التواصل» بنفس نمط a11y — لا كسر للتبويبات السابقة */}
      {/* a11y (WAI-ARIA Tabs): id لكل تبويب + roving tabindex + تنقّل بالأسهم (RTL: يسار=التالي) */}
      <div className="mb-6 flex gap-2 border-b border-slate-200" role="tablist" aria-label="أقسام الاستوديو">
        {(() => {
          const keys = ['curriculum', 'assessments', 'gradebook', 'communications'] as const;
          const labels: Record<(typeof keys)[number], string> = {
            curriculum: 'المنهج',
            assessments: 'التقييمات والدرجات',
            gradebook: t('gradebook.tab'),
            communications: t('comm.tab'),
          };
          const onKeyDown = (e: React.KeyboardEvent, k: (typeof keys)[number]) => {
            const i = keys.indexOf(k);
            let next: (typeof keys)[number] | null = null;
            if (e.key === 'ArrowLeft') next = keys[(i + 1) % keys.length]; // RTL: السهم الأيسر يتقدّم
            else if (e.key === 'ArrowRight') next = keys[(i - 1 + keys.length) % keys.length];
            else if (e.key === 'Home') next = keys[0];
            else if (e.key === 'End') next = keys[keys.length - 1];
            if (next) {
              e.preventDefault();
              setTab(next);
              const id = `tab-${next}`;
              requestAnimationFrame(() => document.getElementById(id)?.focus());
            }
          };
          return keys.map((k) => (
            <button
              key={k}
              id={`tab-${k}`}
              role="tab"
              aria-selected={tab === k}
              aria-controls={`tabpanel-${k}`}
              tabIndex={tab === k ? 0 : -1}
              onClick={() => setTab(k)}
              onKeyDown={(e) => onKeyDown(e, k)}
              className={`-mb-px border-b-2 px-4 py-2.5 text-sm font-bold transition ${
                tab === k ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-600'
              }`}
            >
              {labels[k]}
            </button>
          ));
        })()}
      </div>

      {/* G6: tabpanel يربط بـ aria-controls على كل تبويب — a11y: aria-labelledby للاسم */}
      <div id="tabpanel-assessments" role="tabpanel" aria-labelledby="tab-assessments" hidden={tab !== 'assessments'}>
        <AssessmentsPanel courseSlug={slug} sections={sections} />
      </div>

      {/* C1: tabpanel درجات الطلاب — يُحمَّل عند فتح التبويب */}
      <div id="tabpanel-gradebook" role="tabpanel" aria-labelledby="tab-gradebook" hidden={tab !== 'gradebook'}>
        {tab === 'gradebook' && <InstructorGradebook courseSlug={slug} />}
      </div>

      {/* C3: tabpanel التواصل — يُحمَّل كسلاً عند فتح التبويب فقط */}
      <div id="tabpanel-communications" role="tabpanel" aria-labelledby="tab-communications" hidden={tab !== 'communications'}>
        {tab === 'communications' && <CommunicationsPanel courseSlug={slug} />}
      </div>

      <div id="tabpanel-curriculum" role="tabpanel" aria-labelledby="tab-curriculum" hidden={tab !== 'curriculum'}>
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:order-2">
            <div className="card mb-4">
              <strong className="text-slate-900">{t('studio.cover')}</strong>
              {cover && (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={cover} alt="" className="mt-3 h-28 w-full rounded-xl object-cover" />
              )}
              <label className="btn btn-ghost mt-3 w-full cursor-pointer">
                {cover ? 'استبدال الصورة' : 'رفع صورة'}
                <input type="file" accept="image/*" className="hidden"
                  onChange={(e) => { const f = e.target.files?.[0]; if (f) void uploadCover(f); }} />
              </label>
            </div>

            <form className="card mb-4" onSubmit={(e) => void saveSettings(e)}>
              <strong className="text-slate-900">إعدادات الدورة</strong>
              <label className="label mt-3 block" htmlFor="passing-grade">{t('studio.passingGrade')}</label>
              <input id="passing-grade" className="input" type="number" min="0" max="100" dir="ltr"
                value={passingGrade} onChange={(e) => setPassingGrade(e.target.value)} />
              <p className="mb-3 text-xs text-slate-500">
                عند ضبطها أكبر من صفر، لن تُمنح الشهادة إلا بتحقيق هذه الدرجة في متوسط التقييمات الموزون.
              </p>
              <button className="btn w-full">{t('common.save')}</button>
            </form>

            <form className="card mb-0" onSubmit={(e) => void addSection(e)}>
              <strong className="text-slate-900">{t('studio.addSection')}</strong>
              <label className="label mt-3 block" htmlFor="sec-title">{t('common.title')}</label>
              <input id="sec-title" className="input" value={sectionTitle}
                onChange={(e) => setSectionTitle(e.target.value)} required />
              <button className="btn w-full">{t('common.save')}</button>
            </form>
          </div>

          <div className="lg:col-span-2 lg:order-1">
            {!sections.length ? (
              <div className="card text-slate-500">ابدأ ببناء المنهج: أضف القسم الأول من النموذج المجاور.</div>
            ) : (
              sections.map((s, si) => (
                <div key={s.id} className="card p-0">
                  <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-3.5">
                    <strong className="min-w-0 truncate text-slate-900">القسم {si + 1}: {s.title}</strong>
                    <div className="flex shrink-0 items-center gap-1 text-slate-500">
                      <button title="تحريك لأعلى" className="rounded p-1 hover:bg-slate-100 disabled:opacity-30"
                        disabled={si === 0} onClick={() => void moveSection(si, -1)}>▲</button>
                      <button title="تحريك لأسفل" className="rounded p-1 hover:bg-slate-100 disabled:opacity-30"
                        disabled={si === sections.length - 1} onClick={() => void moveSection(si, 1)}>▼</button>
                      <button title="إعادة تسمية" className="rounded p-1 hover:bg-slate-100" onClick={() => void renameSection(s)}>✎</button>
                      <button title="حذف القسم" className="rounded p-1 text-red-500 hover:bg-red-50" onClick={() => void deleteSection(s)}>✕</button>
                    </div>
                  </div>

                  <ul className="divide-y divide-slate-50">
                    {s.lessons.map((l, li) => (
                      <li key={l.id}>
                        <div className="flex items-center gap-2 px-5 py-3 text-sm text-slate-600">
                          <span className="text-slate-500"><LessonTypeIcon type={l.type} /></span>
                          <button className="min-w-0 flex-1 truncate text-start hover:text-brand-700"
                            onClick={() => setOpenLesson(openLesson === l.id ? null : l.id)}>
                            {l.title}
                            {l.is_free_preview && <span className="badge ms-2">معاينة</span>}
                          </button>
                          <span className="flex shrink-0 items-center gap-1 text-slate-300">
                            <button title="لأعلى" className="rounded p-1 hover:bg-slate-100 disabled:opacity-30"
                              disabled={li === 0} onClick={() => void moveLesson(s, li, -1)}>▲</button>
                            <button title="لأسفل" className="rounded p-1 hover:bg-slate-100 disabled:opacity-30"
                              disabled={li === s.lessons.length - 1} onClick={() => void moveLesson(s, li, 1)}>▼</button>
                            <button className="rounded px-1.5 py-1 text-xs text-brand-600 hover:bg-brand-50"
                              onClick={() => setOpenLesson(openLesson === l.id ? null : l.id)}>
                              {openLesson === l.id ? 'إغلاق' : 'تحرير'}
                            </button>
                          </span>
                        </div>
                        {openLesson === l.id && (
                          <LessonEditor lessonId={l.id} courseSlug={slug} onSaved={load} onDeleted={() => { setOpenLesson(null); load(); }} />
                        )}
                      </li>
                    ))}
                  </ul>

                  <div className="border-t border-slate-100 p-4">
                    <LessonAdder onAdd={(title, kind) => {
                      void api(`/catalog/sections/${s.id}/lessons`, {
                        method: 'POST',
                        body: { title, type: kind, position: s.lessons.length + 1 },
                      }).then(load).catch(() => setNote(t('common.error')));
                    }} />
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </section>
  );
}

function LessonAdder({ onAdd }: { onAdd: (title: string, kind: LessonKind) => void }) {
  const [title, setTitle] = useState('');
  const [kind, setKind] = useState<LessonKind>('article');
  return (
    <form
      onSubmit={(e) => { e.preventDefault(); if (title) { onAdd(title, kind); setTitle(''); } }}
      className="flex flex-wrap gap-2"
    >
      <input className="input m-0 min-w-40 flex-1" placeholder={t('studio.addLesson')}
        value={title} onChange={(e) => setTitle(e.target.value)} />
      <select className="input m-0 w-auto" value={kind} onChange={(e) => setKind(e.target.value as LessonKind)} aria-label="نوع الدرس">
        {LESSON_KINDS.map((k) => <option key={k.value} value={k.value}>{k.label}</option>)}
      </select>
      <button className="btn shrink-0">+ إضافة</button>
    </form>
  );
}
