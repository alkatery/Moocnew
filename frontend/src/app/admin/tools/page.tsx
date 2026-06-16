'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { api, ApiError, API_BASE, getToken } from '@/lib/api';
import type { Course, EnrollmentCodeItem, ImportSummary, Paginated } from '@/lib/types';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';

/** Download a protected CSV: the token lives in localStorage (not a cookie),
 *  so we fetch with the Authorization header and save the Blob. */
async function downloadCsv(path: string, filename: string): Promise<void> {
  const res = await fetch(`${API_BASE}${path}`, {
    headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'text/csv' },
  });
  if (!res.ok) throw new Error('download failed');
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export default function AdminToolsPage() {
  const [courses, setCourses] = useState<Course[]>([]);
  const [error, setError] = useState('');

  // --- CSV import ---
  const fileRef = useRef<HTMLInputElement>(null);
  const [importCourse, setImportCourse] = useState('');
  const [importing, setImporting] = useState(false);
  const [summary, setSummary] = useState<ImportSummary | null>(null);

  // --- enrollment codes ---
  const [codesCourse, setCodesCourse] = useState('');
  const [codes, setCodes] = useState<EnrollmentCodeItem[]>([]);
  const [maxUses, setMaxUses] = useState('');
  const [expiresAt, setExpiresAt] = useState('');
  const [codeBusy, setCodeBusy] = useState(false);
  const [copied, setCopied] = useState<number | null>(null);

  // --- clone ---
  const [cloneCourse, setCloneCourse] = useState('');
  const [cloning, setCloning] = useState(false);
  const [cloneMessage, setCloneMessage] = useState('');

  useEffect(() => {
    api<Paginated<Course>>('/catalog/mine?per_page=50')
      .then((r) => setCourses(r.data))
      .catch(() => setError('تعذّر تحميل قائمة الدورات.'));
  }, []);

  useEffect(() => {
    if (!codesCourse) { setCodes([]); return; }
    api<{ data: EnrollmentCodeItem[] }>(`/admin/courses/${codesCourse}/enrollment-codes`)
      .then((r) => setCodes(r.data))
      .catch(() => setCodes([]));
  }, [codesCourse]);

  async function importCsv(e: React.FormEvent) {
    e.preventDefault();
    const file = fileRef.current?.files?.[0];
    if (!file) return;
    setImporting(true); setError(''); setSummary(null);
    try {
      const form = new FormData();
      form.append('file', file);
      if (importCourse) form.append('course_id', importCourse);
      const res = await fetch(`${API_BASE}/admin/users/import`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'application/json' },
        body: form,
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error((json as { message?: string })?.message ?? 'فشل الاستيراد.');
      setSummary((json as { data: ImportSummary }).data);
      if (fileRef.current) fileRef.current.value = '';
    } catch (err) {
      setError(err instanceof Error ? err.message : 'فشل الاستيراد.');
    } finally {
      setImporting(false);
    }
  }

  async function createCode(e: React.FormEvent) {
    e.preventDefault();
    if (!codesCourse) return;
    setCodeBusy(true); setError('');
    try {
      const body: Record<string, unknown> = {};
      if (maxUses) body.max_uses = Number(maxUses);
      if (expiresAt) body.expires_at = expiresAt;
      const res = await api<{ data: EnrollmentCodeItem }>(`/admin/courses/${codesCourse}/enrollment-codes`, {
        method: 'POST',
        body,
      });
      setCodes((prev) => [res.data, ...prev]);
      setMaxUses(''); setExpiresAt('');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'تعذّر إنشاء الكود.');
    } finally {
      setCodeBusy(false);
    }
  }

  async function deleteCode(code: EnrollmentCodeItem) {
    try {
      await api(`/admin/enrollment-codes/${code.id}`, { method: 'DELETE' });
      setCodes((prev) => prev.filter((c) => c.id !== code.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'تعذّر حذف الكود.');
    }
  }

  async function copyCode(code: EnrollmentCodeItem) {
    try {
      await navigator.clipboard.writeText(code.code);
      setCopied(code.id);
      setTimeout(() => setCopied(null), 1500);
    } catch {
      setError('تعذّر نسخ الكود.');
    }
  }

  async function cloneSelected() {
    if (!cloneCourse) return;
    setCloning(true); setError(''); setCloneMessage('');
    try {
      const res = await api<{ data: Course }>(`/admin/courses/${cloneCourse}/clone`, { method: 'POST' });
      setCloneMessage(`أُنشئت النسخة بنجاح: ${res.data.title}`);
      setCourses((prev) => [res.data, ...prev]);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'تعذّر نسخ الدورة.');
    } finally {
      setCloning(false);
    }
  }

  async function report(path: string, filename: string) {
    setError('');
    try {
      await downloadCsv(path, filename);
    } catch {
      setError('تعذّر تحميل التقرير.');
    }
  }

  const courseOptions = courses.map((c) => (
    <option key={c.id} value={c.slug}>{c.title}</option>
  ));

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>أدوات الإدارة</h1>
        <Link className="btn btn-ghost" href="/admin">لوحة الإدارة</Link>
      </div>
      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={error} />

      <div className="grid gap-6 lg:grid-cols-2">
        {/* CSV import */}
        <form className="card mb-0" onSubmit={(e) => void importCsv(e)}>
          <strong className="text-slate-900">استيراد مستخدمين من CSV</strong>
          <p className="mt-1 text-sm text-slate-500">
            ملف بأعمدة name,email وعمود phone اختياري. يُنشأ كل حساب بدور طالب وكلمة مرور عشوائية، ويُتخطى البريد المكرر (حد أقصى 2000 سطر).
          </p>
          <label className="label mt-3 block" htmlFor="import-file">ملف CSV</label>
          <input id="import-file" ref={fileRef} className="input" type="file" accept=".csv,text/csv" required />
          <label className="label" htmlFor="import-course">إلحاق فوري بدورة (اختياري)</label>
          <select
            id="import-course" className="input" value={importCourse}
            onChange={(e) => setImportCourse(e.target.value)}
          >
            <option value="">بدون إلحاق</option>
            {courses.map((c) => <option key={c.id} value={String(c.id)}>{c.title}</option>)}
          </select>
          <button className="btn w-full" disabled={importing}>
            {importing ? 'جارٍ الاستيراد…' : 'استيراد'}
          </button>

          {summary && (
            <div className="mt-4 rounded-lg bg-slate-50 p-3 text-sm">
              {/* G5: role="status" لرسالة النجاح */}
              <p className="success" role="status">
                أُنشئ: {summary.created} — تُخطي: {summary.skipped} — أُلحق: {summary.enrolled}
              </p>
              {summary.errors.length > 0 && (
                <ul className="mt-2 space-y-1">
                  {summary.errors.map((er, i) => (
                    /* G5: role="alert" لكل سطر خطأ */
                    <li key={i} className="error" role="alert">سطر {er.line}: {er.message}</li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </form>

        {/* Clone course */}
        <div className="card mb-0 self-start">
          <strong className="text-slate-900">نسخ دورة</strong>
          <p className="mt-1 text-sm text-slate-500">
            ينسخ الأقسام والدروس والاختبارات وبنك الأسئلة والواجبات إلى مسودة جديدة — بلا التحاقات أو درجات.
          </p>
          <label className="label mt-3 block" htmlFor="clone-course">الدورة</label>
          <select
            id="clone-course" className="input" value={cloneCourse}
            onChange={(e) => { setCloneCourse(e.target.value); setCloneMessage(''); }}
          >
            <option value="">اختر دورة…</option>
            {courseOptions}
          </select>
          <button className="btn w-full" disabled={cloning || !cloneCourse} onClick={() => void cloneSelected()}>
            {cloning ? 'جارٍ النسخ…' : 'إنشاء نسخة'}
          </button>
          {/* G5: role="status" لرسالة نجاح النسخ */}
          <SuccessMsg msg={cloneMessage} />
        </div>

        {/* Enrollment codes */}
        <div className="card mb-0 lg:col-span-2">
          <strong className="text-slate-900">أكواد الالتحاق الذاتي</strong>
          <p className="mt-1 text-sm text-slate-500">
            أنشئ كوداً قصيراً يلتحق به الطلاب مباشرة عبر صفحة «استخدام كود التحاق».
          </p>

          <div className="mt-3 grid gap-4 md:grid-cols-2">
            <form onSubmit={(e) => void createCode(e)}>
              <label className="label block" htmlFor="codes-course">الدورة</label>
              <select
                id="codes-course" className="input" value={codesCourse}
                onChange={(e) => setCodesCourse(e.target.value)}
              >
                <option value="">اختر دورة…</option>
                {courseOptions}
              </select>
              <label className="label" htmlFor="code-max">حد الاستخدامات (اختياري)</label>
              <input
                id="code-max" className="input" type="number" min={1} dir="ltr"
                value={maxUses} onChange={(e) => setMaxUses(e.target.value)}
              />
              <label className="label" htmlFor="code-expiry">تاريخ الانتهاء (اختياري)</label>
              <input
                id="code-expiry" className="input" type="datetime-local" dir="ltr"
                value={expiresAt} onChange={(e) => setExpiresAt(e.target.value)}
              />
              <button className="btn w-full" disabled={codeBusy || !codesCourse}>
                {codeBusy ? 'جارٍ الإنشاء…' : 'إنشاء كود'}
              </button>
            </form>

            <div>
              {!codesCourse ? (
                <p className="text-sm text-slate-500">اختر دورة لعرض أكوادها.</p>
              ) : codes.length === 0 ? (
                <p className="text-sm text-slate-500">لا توجد أكواد لهذه الدورة بعد.</p>
              ) : (
                <ul className="divide-y divide-slate-100">
                  {codes.map((c) => (
                    <li key={c.id} className="flex flex-wrap items-center gap-2 py-2.5">
                      <code className="rounded bg-slate-100 px-2 py-1 font-mono text-sm font-bold tracking-widest" dir="ltr">
                        {c.code}
                      </code>
                      <span className="badge">
                        {c.used_count}{c.max_uses !== null ? ` / ${c.max_uses}` : ''} استخدام
                      </span>
                      {c.is_expired && <span className="badge bg-red-50 text-red-700">منتهٍ</span>}
                      {c.is_exhausted && <span className="badge bg-red-50 text-red-700">مستنفد</span>}
                      <span className="flex-1" />
                      <button type="button" className="btn btn-ghost px-2.5 py-1.5 text-xs" onClick={() => void copyCode(c)}>
                        {copied === c.id ? 'نُسخ!' : 'نسخ الكود'}
                      </button>
                      <button type="button" className="btn px-2.5 py-1.5 text-xs bg-red-600 hover:bg-red-700" onClick={() => void deleteCode(c)}>
                        حذف
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>

        {/* Reports */}
        <div className="card mb-0 lg:col-span-2">
          <strong className="text-slate-900">تقارير CSV</strong>
          <p className="mt-1 text-sm text-slate-500">
            ملفات CSV بترميز يدعم العربية في Excel مباشرة.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button className="btn" onClick={() => void report('/admin/reports/enrollments.csv', 'enrollments.csv')}>
              تقرير الالتحاقات
            </button>
            <button className="btn" onClick={() => void report('/admin/reports/courses.csv', 'courses.csv')}>
              تقرير الدورات
            </button>
          </div>
        </div>
      </div>
    </section>
  );
}
