'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { api } from '@/lib/api';
import type { GradebookCell, GradebookColumn, GradebookRow, InstructorGradebook } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { ErrorMsg } from '@/components/StatusMessage';

/** مفتاح الفرز: اسم الطالب أو مفتاح عمود (overall | column.key) */
type SortKey = 'name' | 'overall' | string;
type SortDir = 'asc' | 'desc';

/**
 * C1 — جدول درجات الطلاب للمعلّم.
 * يجلب GET /assessment/courses/{slug}/gradebook ويعرض
 * مصفوفة طلاب × عناصر تقييم مع فرز من جانب العميل وتصدير CSV.
 */
export function InstructorGradebook({ courseSlug }: { courseSlug: string }) {
  const [data, setData] = useState<InstructorGradebook | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // حالة الفرز: العمود النشط + الاتجاه
  const [sortKey, setSortKey] = useState<SortKey>('name');
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  // ── جلب البيانات من API ──────────────────────────────────────────────
  const load = useCallback(() => {
    setLoading(true);
    setError('');
    api<{ data: InstructorGradebook }>(`/assessment/courses/${courseSlug}/gradebook`)
      .then((res) => { setData(res.data); })
      .catch(() => { setError(t('gradebook.error')); })
      .finally(() => setLoading(false));
  }, [courseSlug]);

  useEffect(load, [load]);

  // ── منطق الفرز من جانب العميل ────────────────────────────────────────
  const sortedRows = useMemo<GradebookRow[]>(() => {
    if (!data) return [];
    const rows = [...data.rows];
    rows.sort((a, b) => {
      let aVal: string | number | null;
      let bVal: string | number | null;

      if (sortKey === 'name') {
        // فرز نصي: استخدام localeCompare للعربية
        const cmp = a.name.localeCompare(b.name, 'ar');
        return sortDir === 'asc' ? cmp : -cmp;
      } else if (sortKey === 'overall') {
        aVal = a.overall;
        bVal = b.overall;
      } else {
        // مفتاح عمود تقييم
        aVal = a.cells[sortKey]?.score ?? null;
        bVal = b.cells[sortKey]?.score ?? null;
      }

      // null دائماً في النهاية بصرف النظر عن اتجاه الفرز
      if (aVal === null && bVal === null) return 0;
      if (aVal === null) return 1;
      if (bVal === null) return -1;

      const diff = (aVal as number) - (bVal as number);
      return sortDir === 'asc' ? diff : -diff;
    });
    return rows;
  }, [data, sortKey, sortDir]);

  // ── تبديل الفرز عند النقر على رأس العمود ─────────────────────────────
  function handleSort(key: SortKey) {
    if (key === sortKey) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir('asc');
    }
  }

  // ── تصدير CSV (UTF-8 BOM للعربية) ────────────────────────────────────
  function exportCsv() {
    if (!data) return;

    /** تهريب قيمة CSV: إن احتوت فاصلة أو سطراً أو اقتباساً تُلفّ بـ " */
    function csvCell(val: string | number | null): string {
      if (val === null || val === undefined) return '';
      const s = String(val);
      if (s.includes(',') || s.includes('\n') || s.includes('"')) {
        return `"${s.replace(/"/g, '""')}"`;
      }
      return s;
    }

    // رأس الجدول
    const headerCols = data.columns.map((c) => csvCell(c.title));
    const header = [
      csvCell(t('gradebook.student')),
      csvCell('حالة الالتحاق'),
      csvCell(t('gradebook.overall')),
      ...headerCols,
    ].join(',');

    // صفوف الطلاب
    const bodyRows = sortedRows.map((row) => {
      const statusLabel =
        row.enrollment_status === 'active'
          ? t('gradebook.status.active')
          : t('gradebook.status.completed');
      const cells = data.columns.map((col) => {
        const cell = row.cells[col.key];
        return csvCell(cell?.score !== null && cell?.score !== undefined ? cell.score : null);
      });
      return [
        csvCell(row.name),
        csvCell(statusLabel),
        csvCell(row.overall),
        ...cells,
      ].join(',');
    });

    // UTF-8 BOM (﻿) لضمان ظهور العربية صحيحاً في Excel
    const bom = '﻿';
    const content = bom + [header, ...bodyRows].join('\r\n');
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `gradebook-${courseSlug}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ── مساعد: رمز سهم الفرز مع aria-sort ───────────────────────────────
  function SortIcon({ colKey }: { colKey: SortKey }) {
    if (sortKey !== colKey) return <span aria-hidden="true" className="ms-1 text-slate-300">⇅</span>;
    return (
      <span aria-hidden="true" className="ms-1 text-brand-600">
        {sortDir === 'asc' ? '↑' : '↓'}
      </span>
    );
  }

  // ── رأس عمود قابل للفرز ──────────────────────────────────────────────
  function SortableTh({
    colKey,
    children,
    scope = 'col',
    className = '',
  }: {
    colKey: SortKey;
    children: React.ReactNode;
    scope?: 'col' | 'row';
    className?: string;
  }) {
    const isActive = sortKey === colKey;
    const ariaSort: React.AriaAttributes['aria-sort'] = !isActive
      ? 'none'
      : sortDir === 'asc'
        ? 'ascending'
        : 'descending';

    return (
      <th
        scope={scope}
        aria-sort={ariaSort}
        className={`border-b border-slate-200 bg-slate-50 px-3 py-2.5 text-start text-xs font-bold text-slate-700 ${className}`}
      >
        <button
          type="button"
          onClick={() => handleSort(colKey)}
          className="flex items-center gap-0.5 whitespace-nowrap hover:text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600"
          aria-label={`${String(children)} — ${isActive && sortDir === 'desc' ? t('gradebook.sortDesc') : t('gradebook.sortAsc')}`}
        >
          {children}
          <SortIcon colKey={colKey} />
        </button>
      </th>
    );
  }

  // ── حالات: تحميل / خطأ / فراغ ───────────────────────────────────────
  if (loading) {
    return <p className="label py-8 text-center text-slate-500">{t('common.loading')}</p>;
  }

  if (error) {
    return (
      <div className="py-4">
        <ErrorMsg msg={error} />
        <button className="btn btn-ghost mt-2" onClick={load}>
          أعد المحاولة
        </button>
      </div>
    );
  }

  if (!data) return null;

  // لا تقييمات في المقرر
  if (data.columns.length === 0) {
    return (
      <div className="card text-slate-500" role="status">
        {t('grades.empty')}
      </div>
    );
  }

  // لا طلاب ملتحقين
  if (data.rows.length === 0) {
    return (
      <div className="card text-slate-500" role="status">
        {t('gradebook.empty.students')}
      </div>
    );
  }

  // ── الجدول الرئيسي ────────────────────────────────────────────────────
  return (
    <div dir="rtl">
      {/* شريط أدوات: عنوان + تصدير */}
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-bold text-slate-900">
            {t('gradebook.tab')} — {data.course.title}
          </h2>
          {data.course.passing_grade > 0 && (
            <p className="text-xs text-slate-500">
              {t('grades.passing')}: {data.course.passing_grade}%
            </p>
          )}
        </div>
        <button
          type="button"
          onClick={exportCsv}
          className="btn btn-ghost text-sm"
          aria-label={t('gradebook.export')}
        >
          {t('gradebook.export')}
        </button>
      </div>

      {/* الجدول — دلالي RTL مع scope وaria-sort */}
      <div className="overflow-x-auto rounded-xl border border-slate-200">
        <table
          className="w-full min-w-max border-collapse text-sm"
          dir="rtl"
          aria-label={`جدول درجات — ${data.course.title}`}
        >
          <thead>
            <tr>
              {/* عمود اسم الطالب — مثبّت على اليمين (RTL) */}
              <SortableTh
                colKey="name"
                className="sticky end-0 z-10 min-w-[10rem] shadow-[-1px_0_0_0_#e2e8f0]"
              >
                {t('gradebook.student')}
              </SortableTh>

              {/* عمود الدرجة الكلية */}
              <SortableTh colKey="overall" className="min-w-[7rem]">
                {t('gradebook.overall')}
              </SortableTh>

              {/* عمود لكل تقييم */}
              {data.columns.map((col) => (
                <SortableTh key={col.key} colKey={col.key} className="min-w-[8rem]">
                  <span className="flex flex-col gap-0.5">
                    <span className="badge text-[10px]">
                      {col.type === 'quiz' ? t('gradebook.quiz') : t('gradebook.assignment')}
                    </span>
                    <span>{col.title}</span>
                  </span>
                </SortableTh>
              ))}
            </tr>
          </thead>

          <tbody className="divide-y divide-slate-100">
            {sortedRows.map((row) => (
              <tr key={row.user_id} className="hover:bg-slate-50">
                {/* اسم الطالب + شارة الحالة — th scope="row" للدلالية والوصول */}
                <th
                  scope="row"
                  className="sticky end-0 z-10 bg-white px-3 py-2.5 text-start font-normal shadow-[-1px_0_0_0_#e2e8f0]"
                >
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-slate-800">{row.name}</span>
                    <EnrollmentBadge status={row.enrollment_status} />
                  </div>
                </th>

                {/* الدرجة الكلية */}
                <td className="px-3 py-2.5 text-center">
                  <OverallCell overall={row.overall} passed={row.passed} />
                </td>

                {/* خلية لكل تقييم */}
                {data.columns.map((col) => {
                  const cell = row.cells[col.key];
                  return (
                    <td key={col.key} className="px-3 py-2.5 text-center">
                      <ScoreCell cell={cell} />
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* ملاحظة التصدير */}
      <p className="mt-2 text-xs text-slate-500">{t('gradebook.exportNote')}</p>
    </div>
  );
}

// ── مكوّنات مساعدة صغيرة ────────────────────────────────────────────────

/** شارة حالة الالتحاق */
function EnrollmentBadge({ status }: { status: 'active' | 'completed' }) {
  return status === 'completed' ? (
    <span className="badge bg-emerald-50 text-emerald-700 text-[10px]">
      {t('gradebook.status.completed')}
    </span>
  ) : (
    <span className="badge bg-blue-50 text-blue-700 text-[10px]">
      {t('gradebook.status.active')}
    </span>
  );
}

/** خلية الدرجة الكلية: أخضر إن نجح، محايد وإلا، شرطة إن null */
function OverallCell({ overall, passed }: { overall: number | null; passed: boolean }) {
  if (overall === null) {
    return <span className="text-slate-500">—</span>;
  }
  return (
    <strong className={passed ? 'text-emerald-600' : 'text-slate-700'}>
      {overall}%
    </strong>
  );
}

/** خلية درجة تقييم واحد */
function ScoreCell({ cell }: { cell: GradebookCell | undefined }) {
  if (!cell || cell.score === null) {
    return (
      <span className="text-xs text-slate-500">{t('gradebook.notGraded')}</span>
    );
  }
  return (
    <span className={cell.passed ? 'font-bold text-emerald-600' : 'text-slate-700'}>
      {cell.score}%
    </span>
  );
}
