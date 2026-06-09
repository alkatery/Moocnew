'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';
import type { Course, Lesson } from '@/lib/types';
import { t } from '@/i18n/dictionary';

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
    setNote('✓');
  }

  if (!course) return <p className="label">{t('common.loading')}</p>;

  return (
    <section style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 16 }}>
      <aside>
        {course.sections?.map((s) => (
          <div key={s.id} className="card">
            <strong>{s.title}</strong>
            {s.lessons.map((l) => (
              <div key={l.id} style={{ margin: '6px 0' }}>
                <button className="btn" style={{ width: '100%' }} onClick={() => void open(l)}>{l.title}</button>
              </div>
            ))}
          </div>
        ))}
      </aside>
      <div className="card">
        {!active ? (
          <p className="label">{t('lesson.watch')}</p>
        ) : (
          <>
            <h2>{active.title}</h2>
            {playback?.kind === 'embed' && (
              <iframe title={active.title} src={playback.url} style={{ width: '100%', height: 360, border: 0 }} allowFullScreen />
            )}
            {playback?.kind === 'signed_url' && (
              <video controls src={playback.url} style={{ width: '100%' }} />
            )}
            <div style={{ marginTop: 12 }}>
              <button className="btn" onClick={() => void complete(active)}>{t('lesson.complete')}</button>
              {note && <span style={{ marginInlineStart: 8 }}>{note}</span>}
            </div>
          </>
        )}
      </div>
    </section>
  );
}
