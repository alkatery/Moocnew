'use client';

// E3 — التأليف الجماعي: إدارة فريق تأليف المقرر (إضافة/إزالة مؤلّفين مشاركين).
// يظهر هذا المكوّن للمالك فقط — الخادم هو الحارس النهائي (403 على manageMembers).
// العقد: GET/POST /catalog/courses/{slug}/members · DELETE .../members/{user}
//        GET /catalog/courses/{slug}/instructors?q=
// مرجع: docs/contracts/E3-co-authoring.md §4.أ

import { useCallback, useEffect, useRef, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { CourseMember, InstructorSearchResult } from '@/lib/types';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';
import { t } from '@/i18n/dictionary';

// ── ثوابت البحث ──────────────────────────────────────────────────────────────

/** الحد الأدنى لعدد أحرف البحث قبل استدعاء الـ API (PDPL + UX). */
const MIN_SEARCH_CHARS = 2;

// ── الواجهة ───────────────────────────────────────────────────────────────────

interface Props {
  /** slug المقرر — يُستخدم في مسارات الـ API. */
  courseSlug: string;
}

// ── المكوّن الرئيسي ───────────────────────────────────────────────────────────

/**
 * CourseTeamManager — قسم «فريق التأليف» في استوديو المدرّس.
 *
 * يُركَّب في studio/[slug]/page.tsx ضمن تبويب المنهج (العمود الجانبي)،
 * مشروطاً بأن يكون المستخدم الحالي مالك المقرر:
 *   course.instructor?.id === useAuth().user?.id
 */
export function CourseTeamManager({ courseSlug }: Props) {
  // ── الحالة ──────────────────────────────────────────────────────────────────
  const [members, setMembers] = useState<CourseMember[]>([]);
  const [loadState, setLoadState] = useState<'idle' | 'loading' | 'error'>('loading');

  // حقل البحث
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<InstructorSearchResult[]>([]);
  const [searchState, setSearchState] = useState<'idle' | 'loading' | 'error' | 'done'>('idle');

  // رسائل العمليات
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busyId, setBusyId] = useState<number | null>(null); // عضو جارٍ إزالته

  // مرجع timeout لتأخير البحث (debounce)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // ── تحميل الأعضاء ─────────────────────────────────────────────────────────

  const loadMembers = useCallback(async () => {
    setLoadState('loading');
    setError('');
    try {
      const res = await api<{ data: CourseMember[] }>(
        `/catalog/courses/${courseSlug}/members`,
      );
      setMembers(res.data);
      setLoadState('idle');
    } catch {
      setLoadState('error');
      setError(t('team.loadError'));
    }
  }, [courseSlug]);

  useEffect(() => {
    void loadMembers();
  }, [loadMembers]);

  // ── بحث المدرّسين (debounce 350ms) ────────────────────────────────────────

  const search = useCallback(
    (q: string) => {
      // إلغاء debounce السابق
      if (debounceRef.current) clearTimeout(debounceRef.current);

      if (q.length < MIN_SEARCH_CHARS) {
        setResults([]);
        setSearchState('idle');
        return;
      }

      setSearchState('loading');
      debounceRef.current = setTimeout(async () => {
        try {
          const res = await api<{ data: InstructorSearchResult[] }>(
            `/catalog/courses/${courseSlug}/instructors?q=${encodeURIComponent(q)}`,
          );
          setResults(res.data);
          setSearchState('done');
        } catch {
          setResults([]);
          setSearchState('error');
          setError(t('team.searchError'));
        }
      }, 350);
    },
    [courseSlug],
  );

  // تشغيل البحث عند تغيّر النص
  useEffect(() => {
    search(query);
    // تنظيف timeout عند إزالة المكوّن
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [query, search]);

  // ── إضافة عضو ─────────────────────────────────────────────────────────────

  async function addMember(instructor: InstructorSearchResult) {
    setError('');
    setSuccess('');
    try {
      // POST يُعيد القائمة المحدّثة (201) أو القائمة الحالية (200 idempotent)
      const res = await api<{ data: CourseMember[] }>(
        `/catalog/courses/${courseSlug}/members`,
        { method: 'POST', body: { user_id: instructor.id } },
      );
      setMembers(res.data);
      setQuery('');
      setResults([]);
      setSearchState('idle');
      setSuccess(`تمّت إضافة ${instructor.name} إلى فريق التأليف.`);
    } catch (err) {
      // معالجة 422: رسالة الخادم العربية (ليس مدرّساً / المالك نفسه / مكرّر)
      if (err instanceof ApiError && err.status === 422) {
        setError(err.message);
      } else {
        setError(t('team.addError'));
      }
    }
  }

  // ── إزالة عضو ─────────────────────────────────────────────────────────────

  async function removeMember(member: CourseMember) {
    // تأكيد قبل الإزالة
    if (!window.confirm(t('team.removeConfirm').replace('{name}', member.name))) return;

    setError('');
    setSuccess('');
    setBusyId(member.id);
    try {
      // DELETE 204 idempotent — لا جسم استجابة
      await api(`/catalog/courses/${courseSlug}/members/${member.id}`, {
        method: 'DELETE',
      });
      setMembers((prev) => prev.filter((m) => m.id !== member.id));
      setSuccess(`تمّت إزالة ${member.name} من فريق التأليف.`);
    } catch {
      setError(t('team.removeError'));
    } finally {
      setBusyId(null);
    }
  }

  // ── واجهة التحميل الأوّلي ─────────────────────────────────────────────────

  if (loadState === 'loading') {
    return (
      <section className="card mb-4" aria-label={t('team.title')}>
        <p className="text-sm text-slate-500" aria-live="polite">
          {t('common.loading')}
        </p>
      </section>
    );
  }

  // ── العرض الرئيسي ──────────────────────────────────────────────────────────

  return (
    <section className="card mb-4" aria-labelledby="team-heading">
      {/* العنوان */}
      <h3 id="team-heading" className="text-base font-bold text-slate-900">
        {t('team.title')}
      </h3>
      <p className="mb-3 mt-1 text-xs text-slate-500">{t('team.hint')}</p>

      {/* رسائل الحالة */}
      <ErrorMsg msg={error} />
      <SuccessMsg msg={success} />

      {/* قائمة الأعضاء الحاليين */}
      {members.length === 0 ? (
        <p className="mb-3 text-sm text-slate-500" aria-live="polite">
          {t('team.none')}
        </p>
      ) : (
        <ul className="mb-4 divide-y divide-slate-100" aria-label="أعضاء فريق التأليف">
          {members.map((member) => (
            <li
              key={member.id}
              className="flex items-center justify-between gap-2 py-2"
            >
              {/* اسم العضو + شارة الدور */}
              <div className="flex min-w-0 items-center gap-2">
                <span className="truncate text-sm text-slate-700">{member.name}</span>
                <span
                  className="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700"
                  aria-label={`الدور: ${t('team.badge')}`}
                >
                  {t('team.badge')}
                </span>
              </div>

              {/* زر الإزالة */}
              <button
                type="button"
                className="shrink-0 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-red-500 disabled:opacity-50"
                onClick={() => void removeMember(member)}
                disabled={busyId === member.id}
                aria-label={`${t('team.remove')}: ${member.name}`}
                aria-busy={busyId === member.id}
              >
                {busyId === member.id ? t('common.loading') : t('team.remove')}
              </button>
            </li>
          ))}
        </ul>
      )}

      {/* حقل بحث/إضافة مدرّس */}
      <div>
        <label className="label mb-1 block" htmlFor="team-search">
          {t('team.searchLabel')}
        </label>

        {/* حقل النص — combobox pattern: role="combobox" + aria-expanded على div wrapper */}
        <div
          role="combobox"
          aria-expanded={results.length > 0 && searchState === 'done'}
          aria-owns="team-search-results"
          aria-haspopup="listbox"
        >
          <input
            id="team-search"
            type="search"
            className="input mb-2"
            placeholder={t('team.searchPlaceholder')}
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              // مسح رسائل الخطأ السابقة عند الكتابة
              setError('');
            }}
            aria-label={t('team.searchLabel')}
            aria-autocomplete="list"
            aria-controls="team-search-results"
            autoComplete="off"
          />
        </div>

        {/* تلميح الحد الأدنى من الأحرف */}
        {query.length > 0 && query.length < MIN_SEARCH_CHARS && (
          <p className="mb-2 text-xs text-slate-500" aria-live="polite">
            {t('team.minChars')}
          </p>
        )}

        {/* مؤشر التحميل أثناء البحث */}
        {searchState === 'loading' && (
          <p className="mb-2 text-xs text-slate-500" aria-live="polite">
            {t('common.loading')}
          </p>
        )}

        {/* قائمة نتائج البحث */}
        {searchState === 'done' && query.length >= MIN_SEARCH_CHARS && (
          <ul
            id="team-search-results"
            role="listbox"
            aria-label="نتائج بحث المدرّسين"
            className="mb-2 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white"
          >
            {results.length === 0 ? (
              <li className="px-3 py-2 text-sm text-slate-500" role="option" aria-selected={false}>
                {t('team.noResults')}
              </li>
            ) : (
              results.map((instructor) => {
                // استبعاد الأعضاء المضافين مسبقاً من قائمة النتائج
                const alreadyMember = members.some((m) => m.id === instructor.id);
                return (
                  <li key={instructor.id} role="option" aria-selected={false}>
                    <button
                      type="button"
                      className="flex w-full items-center justify-between gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-500 disabled:opacity-50"
                      onClick={() => void addMember(instructor)}
                      disabled={alreadyMember}
                      aria-label={`${t('team.add')}: ${instructor.name}${alreadyMember ? ' (مضاف مسبقاً)' : ''}`}
                    >
                      <span>{instructor.name}</span>
                      {alreadyMember ? (
                        <span className="text-xs text-slate-400">مضاف</span>
                      ) : (
                        <span className="shrink-0 text-xs font-medium text-brand-600">
                          + {t('team.add')}
                        </span>
                      )}
                    </button>
                  </li>
                );
              })
            )}
          </ul>
        )}
      </div>
    </section>
  );
}
