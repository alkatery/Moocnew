'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { Course } from '@/lib/types';
import { t } from '@/i18n/dictionary';

export default function ManageCoursePage() {
  const { slug } = useParams<{ slug: string }>();
  const [course, setCourse] = useState<Course | null>(null);
  const [sectionTitle, setSectionTitle] = useState('');
  const [note, setNote] = useState('');

  function load() {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((r) => setCourse(r.data))
      .catch(() => setCourse(null));
  }
  useEffect(load, [slug]);

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
      <h1>{course.title} <span className="badge">{course.status}</span></h1>
      <form className="card" onSubmit={addSection}>
        <strong>{t('studio.addSection')}</strong>
        <input className="input" value={sectionTitle} onChange={(e) => setSectionTitle(e.target.value)} required />
        <button className="btn">{t('common.save')}</button>
      </form>
      {course.sections?.map((s) => (
        <div key={s.id} className="card">
          <strong>{s.title}</strong>
          <ul>{s.lessons.map((l) => <li key={l.id}>{l.title}</li>)}</ul>
          <LessonAdder onAdd={(title) => void addLesson(s.id, title)} />
        </div>
      ))}
      <button className="btn" onClick={() => void submit()} style={{ marginTop: 12 }}>{t('studio.submit')}</button>
      {note && <span style={{ marginInlineStart: 8 }}>{note}</span>}
    </section>
  );
}

function LessonAdder({ onAdd }: { onAdd: (title: string) => void }) {
  const [title, setTitle] = useState('');
  return (
    <form
      onSubmit={(e) => { e.preventDefault(); if (title) { onAdd(title); setTitle(''); } }}
      style={{ display: 'flex', gap: 8, marginTop: 8 }}
    >
      <input className="input" style={{ margin: 0 }} placeholder={t('studio.addLesson')} value={title} onChange={(e) => setTitle(e.target.value)} />
      <button className="btn">+</button>
    </form>
  );
}
