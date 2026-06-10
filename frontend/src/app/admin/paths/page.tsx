'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { Course, Paginated, PathDetail, PathSummary } from '@/lib/types';
import { t } from '@/i18n/dictionary';

interface DraftItem {
  course_id: number;
  title: string;
  level: number;
  position: number;
}

export default function AdminPathsPage() {
  const [paths, setPaths] = useState<PathSummary[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  // Create form.
  const [title, setTitle] = useState('');
  const [summary, setSummary] = useState('');
  const [publish, setPublish] = useState(false);

  // Items editor.
  const [editing, setEditing] = useState<PathSummary | null>(null);
  const [items, setItems] = useState<DraftItem[]>([]);
  const [pickCourse, setPickCourse] = useState<number | ''>('');
  const [pickLevel, setPickLevel] = useState(1);

  async function load() {
    try {
      const res = await api<Paginated<PathSummary>>('/learning/paths?include_drafts=1');
      setPaths(res.data);
    } catch { setError(t('common.error')); }
  }

  useEffect(() => {
    void load();
    void api<Paginated<Course>>('/catalog/courses?per_page=50', { auth: false })
      .then((r) => setCourses(r.data)).catch(() => undefined);
  }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setError('');
    try {
      await api('/learning/paths', {
        method: 'POST',
        body: { title, summary: summary || undefined, published: publish },
      });
      setTitle(''); setSummary(''); setPublish(false);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally { setBusy(false); }
  }

  async function togglePublish(path: PathSummary) {
    try {
      await api(`/learning/paths/${path.slug}`, {
        method: 'PATCH',
        body: { published: !path.published_at },
      });
      await load();
    } catch { setError(t('common.error')); }
  }

  async function remove(path: PathSummary) {
    try {
      await api(`/learning/paths/${path.slug}`, { method: 'DELETE' });
      if (editing?.id === path.id) setEditing(null);
      await load();
    } catch { setError(t('common.error')); }
  }

  async function openEditor(path: PathSummary) {
    setEditing(path);
    setItems([]);
    try {
      const res = await api<{ data: PathDetail }>(`/learning/paths/${path.slug}`);
      setItems(res.data.levels.flatMap((lvl) =>
        lvl.items.map((i) => ({
          course_id: i.course.id,
          title: i.course.title,
          level: lvl.level,
          position: i.position,
        })),
      ));
    } catch { setError(t('common.error')); }
  }

  function addItem() {
    if (pickCourse === '' || items.some((i) => i.course_id === pickCourse)) return;
    const course = courses.find((c) => c.id === pickCourse);
    if (!course) return;
    const position = items.filter((i) => i.level === pickLevel).length + 1;
    setItems([...items, { course_id: course.id, title: course.title, level: pickLevel, position }]);
    setPickCourse('');
  }

  async function saveItems() {
    if (!editing) return;
    setBusy(true); setError('');
    try {
      await api(`/learning/paths/${editing.slug}/items`, {
        method: 'PUT',
        body: { items: items.map(({ course_id, level, position }) => ({ course_id, level, position })) },
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally { setBusy(false); }
  }

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>إدارة المسارات</h1>
        <Link className="btn btn-ghost" href="/admin">{t('admin.title')}</Link>
      </div>
      {error && <p className="error mb-4">{error}</p>}

      <div className="grid gap-6 lg:grid-cols-2">
        <div>
          <form className="card" onSubmit={(e) => void create(e)}>
            <strong className="text-slate-900">مسار جديد</strong>
            <label className="label mt-3 block" htmlFor="pt-title">{t('common.title')}</label>
            <input id="pt-title" className="input" required maxLength={255}
              value={title} onChange={(e) => setTitle(e.target.value)} />
            <label className="label" htmlFor="pt-summary">وصف مختصر</label>
            <input id="pt-summary" className="input" maxLength={500}
              value={summary} onChange={(e) => setSummary(e.target.value)} />
            <label className="mb-3 flex items-center gap-2 text-sm text-slate-600">
              <input type="checkbox" checked={publish} onChange={(e) => setPublish(e.target.checked)} />
              نشر فوراً
            </label>
            <button className="btn w-full" disabled={busy}>{t('studio.create')}</button>
          </form>

          {paths.map((p) => (
            <div key={p.id} className="card">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <strong className="text-slate-900">{p.title}</strong>
                  <div className="mt-1 flex items-center gap-2 text-xs">
                    <span className={`badge ${p.published_at ? '' : 'bg-amber-50 text-amber-700'}`}>
                      {p.published_at ? 'منشور' : 'مسودة'}
                    </span>
                    <span className="text-slate-400">{p.levels_count} مستويات · {p.courses_count} دورات</span>
                  </div>
                </div>
                <div className="page-actions">
                  <button className="btn btn-ghost" onClick={() => void openEditor(p)}>الدورات والمستويات</button>
                  <button className="btn btn-ghost" onClick={() => void togglePublish(p)}>
                    {p.published_at ? 'إلغاء النشر' : 'نشر'}
                  </button>
                  <button className="btn bg-red-600 hover:bg-red-700" onClick={() => void remove(p)}>حذف</button>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Items editor */}
        <div>
          {editing ? (
            <div className="card sticky top-20">
              <strong className="text-slate-900">عناصر المسار: {editing.title}</strong>
              <p className="mt-1 text-xs text-slate-400">يأخذ المتدرب الدورات بترتيب (المستوى ثم الموضع).</p>

              <div className="mt-4 flex flex-wrap items-end gap-2">
                <div className="min-w-40 flex-1">
                  <label className="label" htmlFor="pick-course">الدورة</label>
                  <select id="pick-course" className="input m-0 mt-1.5" value={pickCourse}
                    onChange={(e) => setPickCourse(e.target.value === '' ? '' : Number(e.target.value))}>
                    <option value="">— اختر دورة —</option>
                    {courses.filter((c) => !items.some((i) => i.course_id === c.id)).map((c) => (
                      <option key={c.id} value={c.id}>{c.title}</option>
                    ))}
                  </select>
                </div>
                <div className="w-24">
                  <label className="label" htmlFor="pick-level">المستوى</label>
                  <input id="pick-level" className="input m-0 mt-1.5" type="number" min={1} max={50}
                    value={pickLevel} onChange={(e) => setPickLevel(Math.max(1, Number(e.target.value)))} />
                </div>
                <button type="button" className="btn shrink-0" onClick={addItem}>إضافة</button>
              </div>

              {items.length === 0 ? (
                <p className="mt-4 text-sm text-slate-400">لا توجد دورات في المسار بعد.</p>
              ) : (
                <ul className="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">
                  {[...items].sort((a, b) => a.level - b.level || a.position - b.position).map((item) => (
                    <li key={item.course_id} className="flex items-center gap-3 px-4 py-3 text-sm">
                      <span className="badge shrink-0">م{item.level} · {item.position}</span>
                      <span className="flex-1 text-slate-700">{item.title}</span>
                      <button type="button" className="text-red-600 hover:underline"
                        onClick={() => setItems(items.filter((i) => i.course_id !== item.course_id))}>
                        إزالة
                      </button>
                    </li>
                  ))}
                </ul>
              )}

              <div className="page-actions mt-4">
                <button className="btn" onClick={() => void saveItems()} disabled={busy}>
                  {busy ? t('common.loading') : 'حفظ الترتيب'}
                </button>
                <button className="btn btn-ghost" onClick={() => setEditing(null)}>إغلاق</button>
              </div>
            </div>
          ) : (
            <div className="card text-center text-sm text-slate-400">
              اختر «الدورات والمستويات» من أي مسار لتحرير محتواه.
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
