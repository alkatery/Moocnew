'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import type { CourseGrade } from '@/lib/types';
import { t } from '@/i18n/dictionary';

/**
 * The learner's gradebook for a course (Edraak-style "درجاتي"): overall
 * grade against the required passing grade, plus each assessment's score.
 * Renders nothing when the course has no assessments.
 */
export function Gradebook({ courseSlug }: { courseSlug: string }) {
  const [grade, setGrade] = useState<CourseGrade | null>(null);

  useEffect(() => {
    api<{ data: CourseGrade }>(`/assessment/courses/${courseSlug}/grade`)
      .then((r) => setGrade(r.data))
      .catch(() => setGrade(null));
  }, [courseSlug]);

  if (!grade || grade.components.length === 0) return null;

  const overall = grade.overall ?? 0;

  return (
    <div className="card">
      <div className="mb-3 flex items-center justify-between">
        <strong className="text-slate-900">{t('grades.title')}</strong>
        {grade.passing_grade > 0 && (
          <span className="text-xs text-slate-400">{t('grades.passing')}: {grade.passing_grade}%</span>
        )}
      </div>

      <div className="mb-3 flex items-end justify-between">
        <span className="text-sm text-slate-500">{t('grades.overall')}</span>
        <span className={`text-3xl font-extrabold ${grade.passed ? 'text-emerald-600' : 'text-brand-700'}`}>
          {grade.overall === null ? '—' : `${overall}%`}
        </span>
      </div>
      <div className="progress mb-2">
        <span className={grade.passed ? 'bg-emerald-500' : undefined} style={{ width: `${overall}%` }} />
      </div>
      <p className={`mb-4 text-sm ${grade.passed ? 'text-emerald-700' : 'text-slate-500'}`}>
        {grade.passed ? t('grades.passed') : t('grades.notYet')}
      </p>

      <ul className="divide-y divide-slate-100">
        {grade.components.map((c, i) => (
          <li key={i} className="flex items-center justify-between py-2.5 text-sm">
            <span className="flex items-center gap-2 text-slate-700">
              <span className="badge">{c.type === 'quiz' ? 'اختبار' : 'واجب'}</span>
              {c.type === 'quiz' && c.id
                ? <Link className="hover:text-brand-700" href={`/quiz/${c.id}`}>{c.title} ←</Link>
                : c.title}
            </span>
            <span className="flex items-center gap-2">
              {c.score === null ? (
                <span className="text-xs text-slate-400">{t('grades.notGraded')}</span>
              ) : (
                <strong className={c.passed ? 'text-emerald-600' : 'text-slate-700'}>{c.score}%</strong>
              )}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}
