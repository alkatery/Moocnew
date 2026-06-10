'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { API_BASE, api, getToken } from '@/lib/api';
import type { Course } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';
import { HelpGuide } from '@/components/HelpGuide';
import { badgeTone, statusLabel } from '@/lib/labels';

export default function ManageCoursePage() {
  const { slug } = useParams<{ slug: string }>();
  const [course, setCourse] = useState<Course | null>(null);
  const [sectionTitle, setSectionTitle] = useState('');
  const [passingGrade, setPassingGrade] = useState('0');
  const [note, setNote] = useState('');
  const [cover, setCover] = useState<string | null>(null);

  function load() {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((r) => { setCourse(r.data); setPassingGrade(String(r.data.passing_grade ?? 0)); setCover(r.data.cover_image ?? null); })
      .catch(() => setCourse(null));
  }
  useEffect(load, [slug]);

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
    } catch {
      setNote(t('common.error'));
    }
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
    } catch {
      setNote(t('common.error'));
    }
  }

  async function addSection(e: React.FormEvent) {
    e.preventDefault();
    await api(`/catalog/courses/${slug}/sections`, { method: 'POST', body: { title: sectionTitle } });
    setSectionTitle('');
    load();
  }

  async function addLesson(sectionId: number, title: string) {
    await api(`/catalog/sections/${sectionId}/lessons`, { method: 'POST', body: { title, type: 'article' } });
    load();
  }

  async function submit() {
    try {
      await api(`/catalog/courses/${slug}/submit`, { method: 'POST' });
      setNote(t('studio.submit') + ' ✓');
      load();
    } catch {
      setNote(t('common.error'));
    }
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
            {note && <span className="success self-center">{note}</span>}
            <button className="btn" onClick={() => void submit()}>{t('studio.submit')}</button>
          </>
        }
      />

      <HelpGuide
        title="دليل بناء هذه الدورة"
        intro="هذه صفحة بناء الدورة. ابنِ المحتوى من اليمين، واضبط الإعدادات وصورة الغلاف من البطاقات الجانبية، ثم أرسلها للمراجعة."
        steps={[
          { title: 'صورة الغلاف', body: 'ارفع صورة معبّرة من بطاقة «صورة غلاف الدورة» — تظهر في الكتالوج وصفحة الدورة.' },
          { title: 'إعدادات الدورة', body: 'اضبط «درجة النجاح المطلوبة». 0 تعني دورة بلا تقييم تُمنح شهادتها بإكمال الدروس فقط؛ أي قيمة أكبر تشترط اجتياز الاختبارات بتلك الدرجة.' },
          { title: 'أضف الأقسام', body: 'من بطاقة «إضافة قسم» أنشئ وحدات الدورة بالترتيب (مثل: مقدمة، الأساسيات، تطبيقات...).' },
          { title: 'أضف الدروس داخل كل قسم', body: 'استخدم حقل «إضافة درس» أسفل كل قسم. تُنشأ الدروس كمقالات، ويمكن لاحقاً جعلها فيديو أو ملفاً أو جلسة مباشرة.' },
          { title: 'إرسال للمراجعة', body: 'حين يكتمل المنهج اضغط «إرسال للمراجعة» في الأعلى؛ بعد موافقة الإدارة تُنشر الدورة وتصبح متاحة للالتحاق.' },
        ]}
      />

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
            <p className="mb-3 text-xs text-slate-400">
              عند ضبطها أكبر من صفر، لن تُمنح الشهادة إلا باجتياز اختبارات وواجبات الدورة بهذه الدرجة.
            </p>
            <button className="btn w-full">{t('common.save')}</button>
          </form>

          <form className="card mb-0" onSubmit={addSection}>
            <strong className="text-slate-900">{t('studio.addSection')}</strong>
            <label className="label mt-3 block" htmlFor="sec-title">{t('common.title')}</label>
            <input id="sec-title" className="input" value={sectionTitle}
              onChange={(e) => setSectionTitle(e.target.value)} required />
            <button className="btn w-full">{t('common.save')}</button>
          </form>
        </div>

        <div className="lg:col-span-2 lg:order-1">
          {!course.sections?.length ? (
            <div className="card text-slate-500">ابدأ ببناء المنهج: أضف القسم الأول من النموذج المجاور.</div>
          ) : (
            course.sections.map((s, si) => (
              <div key={s.id} className="card p-0">
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                  <strong className="text-slate-900">القسم {si + 1}: {s.title}</strong>
                  <span className="text-xs text-slate-400">{s.lessons.length} دروس</span>
                </div>
                <ul className="divide-y divide-slate-50">
                  {s.lessons.map((l) => (
                    <li key={l.id} className="flex items-center gap-3 px-5 py-3 text-sm text-slate-600">
                      <span className="text-slate-400"><LessonTypeIcon type={l.type} /></span>
                      {l.title}
                    </li>
                  ))}
                </ul>
                <div className="border-t border-slate-100 p-4">
                  <LessonAdder onAdd={(title) => void addLesson(s.id, title)} />
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </section>
  );
}

function LessonAdder({ onAdd }: { onAdd: (title: string) => void }) {
  const [title, setTitle] = useState('');
  return (
    <form
      onSubmit={(e) => { e.preventDefault(); if (title) { onAdd(title); setTitle(''); } }}
      className="flex gap-2"
    >
      <input className="input m-0 flex-1" placeholder={t('studio.addLesson')}
        value={title} onChange={(e) => setTitle(e.target.value)} />
      <button className="btn shrink-0">+</button>
    </form>
  );
}
