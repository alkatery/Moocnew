'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { AdminUser, Paginated } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t, type TranslationKey } from '@/i18n/dictionary';
import { ErrorMsg } from '@/components/StatusMessage';

const ROLES = ['instructor', 'supervisor', 'student'] as const;
type CreatableRole = (typeof ROLES)[number];

function roleLabel(role: string): string {
  return t(`role.${role}` as TranslationKey);
}

const EMPTY = { name: '', email: '', password: '', role: 'instructor' as CreatableRole };

/** صفّ دورة في لوحة «دورات المدرّس» (GET /admin/users/{id}/courses). */
interface InstructorCourse {
  id: number;
  title: string;
  slug: string;
  status: string;
  enrollments_count: number;
}

const COURSE_STATUS_LABEL: Record<string, string> = {
  draft: 'مسودة',
  pending_review: 'قيد المراجعة',
  published: 'منشور',
  archived: 'مؤرشف',
};

export default function AdminUsersPage() {
  const { impersonate } = useAuth();
  const router = useRouter();
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [q, setQ] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [form, setForm] = useState(EMPTY);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  // نقل الدورة: المدرّس المُوسَّع وقائمة دوراته، وخيارات المدرّسين للنقل إليهم.
  const [coursesOf, setCoursesOf] = useState<number | null>(null);
  const [userCourses, setUserCourses] = useState<InstructorCourse[]>([]);
  const [instructors, setInstructors] = useState<AdminUser[]>([]);
  const [transferSel, setTransferSel] = useState<Record<number, number | ''>>({});
  const [coursesBusy, setCoursesBusy] = useState(false);

  // قائمة المدرّسين الوجهة للنقل (تُجلب مرّة واحدة).
  useEffect(() => {
    api<Paginated<AdminUser>>('/admin/users?role=instructor')
      .then((r) => setInstructors(r.data))
      .catch(() => undefined);
  }, []);

  function load() {
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (roleFilter) params.set('role', roleFilter);
    const qs = params.toString();
    api<Paginated<AdminUser>>(`/admin/users${qs ? `?${qs}` : ''}`)
      .then((r) => setUsers(r.data))
      .catch(() => setError(t('common.error')));
  }

  useEffect(() => {
    const id = setTimeout(load, 250); // debounce search
    return () => clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q, roleFilter]);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setError('');
    try {
      await api('/admin/users', { method: 'POST', body: form });
      setForm(EMPTY);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  async function changeRole(user: AdminUser, role: string) {
    try {
      await api(`/admin/users/${user.id}/role`, { method: 'PATCH', body: { role } });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  async function toggleStatus(user: AdminUser & { disabled?: boolean }) {
    try {
      await api(`/admin/users/${user.id}/status`, { method: 'PATCH', body: { disabled: !user.disabled } });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  async function resetPassword(user: AdminUser) {
    try {
      const res = await api<{ data: { password: string } }>(`/admin/users/${user.id}/reset-password`, { method: 'POST' });
      window.alert(`${t('users.newPassword')}\n\n${res.data.password}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  async function remove(user: AdminUser) {
    if (!window.confirm(`${t('users.delete')}: ${user.name}؟`)) return;
    try {
      await api(`/admin/users/${user.id}`, { method: 'DELETE' });
      setUsers((prev) => prev.filter((u) => u.id !== user.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  async function retire(user: AdminUser) {
    if (!window.confirm(t('users.retireConfirm').replace('{name}', user.name))) return;
    try {
      await api(`/admin/users/${user.id}/retire`, { method: 'POST' });
      setUsers((prev) => prev.filter((u) => u.id !== user.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  async function loginAs(user: AdminUser) {
    setError('');
    try {
      const res = await api<{ token: string; user: AdminUser }>(`/admin/users/${user.id}/impersonate`, { method: 'POST' });
      await impersonate(res.token, user.name);
      router.push('/learn');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    }
  }

  // فتح/طيّ لوحة دورات المدرّس (لعرضها ونقلها).
  async function viewCourses(user: AdminUser) {
    if (coursesOf === user.id) { setCoursesOf(null); return; }
    setCoursesBusy(true); setError(''); setCoursesOf(user.id); setUserCourses([]);
    try {
      const res = await api<{ data: { courses: InstructorCourse[] } }>(`/admin/users/${user.id}/courses`);
      setUserCourses(res.data.courses);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
      setCoursesOf(null);
    } finally { setCoursesBusy(false); }
  }

  // نقل دورة إلى مدرّس آخر. تختفي الدورة من قائمة المالك الحالي بعد النقل.
  async function transferCourse(course: InstructorCourse) {
    const targetId = transferSel[course.id];
    if (targetId === '' || targetId === undefined) return;
    const target = instructors.find((i) => i.id === targetId);
    if (!window.confirm(`نقل «${course.title}» إلى ${target?.name ?? 'المدرّس المحدّد'}؟`)) return;
    setCoursesBusy(true); setError('');
    try {
      await api(`/admin/courses/${course.slug}/instructor`, { method: 'PATCH', body: { instructor_id: targetId } });
      setUserCourses((prev) => prev.filter((c) => c.id !== course.id));
      setTransferSel((prev) => { const next = { ...prev }; delete next[course.id]; return next; });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally { setCoursesBusy(false); }
  }

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>{t('users.title')}</h1>
        <Link className="btn btn-ghost" href="/admin">{t('admin.title')}</Link>
      </div>
      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={error} />

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Create user */}
        <form className="card mb-0 self-start lg:order-2" onSubmit={(e) => void create(e)}>
          <strong className="text-slate-900">{t('users.new')}</strong>
          <label className="label mt-3 block" htmlFor="u-name">{t('auth.name')}</label>
          <input id="u-name" className="input" required value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <label className="label" htmlFor="u-email">{t('auth.email')}</label>
          <input id="u-email" className="input" type="email" dir="ltr" required value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <label className="label" htmlFor="u-pass">{t('auth.password')}</label>
          <input id="u-pass" className="input" type="text" dir="ltr" required minLength={8} value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })} />
          <label className="label" htmlFor="u-role">{t('users.role')}</label>
          <select id="u-role" className="input" value={form.role}
            onChange={(e) => setForm({ ...form, role: e.target.value as CreatableRole })}>
            {ROLES.map((r) => <option key={r} value={r}>{roleLabel(r)}</option>)}
          </select>
          <button className="btn w-full" disabled={busy}>
            {busy ? t('common.loading') : t('users.create')}
          </button>
        </form>

        {/* List */}
        <div className="lg:col-span-2 lg:order-1">
          <div className="mb-4 flex flex-wrap gap-2">
            <input className="input m-0 flex-1" placeholder={t('users.search')}
              value={q} onChange={(e) => setQ(e.target.value)} />
            <select className="input m-0 w-40" value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)}>
              <option value="">{t('users.all')}</option>
              {ROLES.map((r) => <option key={r} value={r}>{roleLabel(r)}</option>)}
            </select>
          </div>

          {users.length === 0 ? (
            <div className="card text-center text-slate-500">{t('users.empty')}</div>
          ) : (
            <div className="card p-0">
              <ul className="divide-y divide-slate-100">
                {users.map((u) => {
                  const isSuper = u.roles.includes('super_admin');
                  const role = u.roles[0] ?? 'student';
                  return (
                    <li key={u.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 font-extrabold text-brand-700">
                        {u.name.slice(0, 1)}
                      </span>
                      <div className="min-w-0 flex-1">
                        <strong className="block truncate text-slate-900">
                          {u.name}
                          {u.disabled && <span className="badge ms-2 bg-red-50 text-red-700">{t('users.disabled')}</span>}
                        </strong>
                        <span className="text-xs text-slate-500" dir="ltr">{u.email}</span>
                      </div>
                      {isSuper ? (
                        <span className="badge bg-slate-100 text-slate-600">{roleLabel('super_admin')}</span>
                      ) : (
                        <select
                          className="input m-0 w-24 py-1.5 text-xs"
                          value={role}
                          onChange={(e) => void changeRole(u, e.target.value)}
                          aria-label={t('users.changeRole')}
                        >
                          {ROLES.map((r) => <option key={r} value={r}>{roleLabel(r)}</option>)}
                        </select>
                      )}
                      {!isSuper && (
                        <div className="flex flex-wrap gap-1.5">
                          {u.roles.includes('instructor') && (
                            <button className="btn btn-ghost px-2.5 py-1.5 text-xs" aria-expanded={coursesOf === u.id}
                              onClick={() => void viewCourses(u)}>
                              دوراته
                            </button>
                          )}
                          <button className="btn btn-ghost px-2.5 py-1.5 text-xs" onClick={() => void loginAs(u)}>
                            {t('users.impersonate')}
                          </button>
                          <button className="btn btn-ghost px-2.5 py-1.5 text-xs" onClick={() => void toggleStatus(u)}>
                            {u.disabled ? t('users.enable') : t('users.disable')}
                          </button>
                          <button className="btn btn-ghost px-2.5 py-1.5 text-xs" onClick={() => void resetPassword(u)}>
                            {t('users.resetPassword')}
                          </button>
                          <button className="btn btn-ghost px-2.5 py-1.5 text-xs text-amber-700 ring-amber-200" onClick={() => void retire(u)}>
                            {t('users.retire')}
                          </button>
                          <button className="btn px-2.5 py-1.5 text-xs bg-red-600 hover:bg-red-700" onClick={() => void remove(u)}>
                            {t('users.delete')}
                          </button>
                        </div>
                      )}

                      {/* لوحة دورات المدرّس + نقلها لمدرّس آخر */}
                      {coursesOf === u.id && (
                        <div className="basis-full">
                          {coursesBusy && userCourses.length === 0 ? (
                            <p className="py-2 text-sm text-slate-500">{t('common.loading')}</p>
                          ) : userCourses.length === 0 ? (
                            <p className="py-2 text-sm text-slate-500">لا توجد دورات لهذا المدرّس.</p>
                          ) : (
                            <ul className="mt-1 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-slate-50">
                              {userCourses.map((c) => (
                                <li key={c.id} className="flex flex-wrap items-center gap-2 px-4 py-3 text-sm">
                                  <div className="min-w-0 flex-1">
                                    <strong className="block truncate text-slate-800">{c.title}</strong>
                                    <span className="text-xs text-slate-500">
                                      <span className="badge me-1">{COURSE_STATUS_LABEL[c.status] ?? c.status}</span>
                                      {c.enrollments_count} ملتحق
                                    </span>
                                  </div>
                                  <select className="input m-0 w-40 py-1.5 text-xs" aria-label={`نقل ${c.title} إلى مدرّس`}
                                    value={transferSel[c.id] ?? ''}
                                    onChange={(e) => setTransferSel((p) => ({ ...p, [c.id]: e.target.value === '' ? '' : Number(e.target.value) }))}>
                                    <option value="">— نقل إلى —</option>
                                    {instructors.filter((i) => i.id !== u.id).map((i) => (
                                      <option key={i.id} value={i.id}>{i.name}</option>
                                    ))}
                                  </select>
                                  <button className="btn px-2.5 py-1.5 text-xs" disabled={coursesBusy || !transferSel[c.id]}
                                    onClick={() => void transferCourse(c)}>
                                    نقل
                                  </button>
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                      )}
                    </li>
                  );
                })}
              </ul>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
