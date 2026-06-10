'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { Course } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';
import { badgeTone, statusLabel } from '@/lib/labels';

export default function ManageCoursePage() {
  const { slug } = useParams<{ slug: string }>();
  const [course, setCourse] = useState<Course | null>(null);
  const [sectionTitle, setSectionTitle] = useState('');
  const [passingGrade, setPassingGrade] = useState('0');
  const [note, setNote] = useState('');

  function load() {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((r) => { setCourse(r.data); setPassingGrade(String(r.data.passing_grade ?? 0)); })
      .catch(() => setCourse(null));
  }
  useEffect(load, [slug]);

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

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:order-2">
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
