'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Course } from '@/lib/types';
import { t } from '@/i18n/dictionary';

export default function CourseDetailPage() {
  const { slug } = useParams<{ slug: string }>();
  const { user } = useAuth();
  const router = useRouter();
  const [course, setCourse] = useState<Course | null>(null);
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ data: Course }>(`/catalog/courses/${slug}`, { auth: false })
      .then((res) => setCourse(res.data))
      .catch(() => setCourse(null));
  }, [slug]);

  async function enroll() {
    if (!user) {
      router.push('/login');
      return;
    }
    setBusy(true);
    setMsg('');
    try {
      await api(`/catalog/courses/${slug}/enroll`, { method: 'POST' });
      router.push(`/learn/${slug}`);
    } catch (err) {
      setMsg(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  if (!course) return <p className="label">{t('common.loading')}</p>;

  return (
    <section>
      <h1>{course.title}</h1>
      <p className="label">{course.description ?? course.summary}</p>
      {course.sections?.map((s) => (
        <div key={s.id} className="card">
          <strong>{s.title}</strong>
          <ul>{s.lessons.map((l) => <li key={l.id}>{l.title}</li>)}</ul>
        </div>
      ))}
      {msg && <p className="error">{msg}</p>}
      <button className="btn" onClick={() => void enroll()} disabled={busy}>{t('course.enroll')}</button>
    </section>
  );
}
