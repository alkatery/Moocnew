'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import type { Category } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { ErrorMsg } from '@/components/StatusMessage';

/**
 * إدارة تصنيفات الكتالوج — كانت العملية (POST /catalog/categories، صلاحية
 * categories.manage) بلا مدخل من لوحة التحكم. تُنشئ التصنيفات هنا وتُسرَد،
 * مع دعم التصنيف الأب (شجرة) والموضع.
 */
export default function AdminCategoriesPage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [name, setName] = useState('');
  const [parentId, setParentId] = useState<number | ''>('');
  const [position, setPosition] = useState<number | ''>('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  function load() {
    api<{ data: Category[] }>('/catalog/categories', { auth: false })
      .then((r) => setCategories(r.data))
      .catch(() => setError(t('common.error')));
  }

  useEffect(load, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api('/catalog/categories', {
        method: 'POST',
        body: {
          name,
          parent_id: parentId === '' ? undefined : parentId,
          position: position === '' ? undefined : position,
        },
      });
      setName('');
      setParentId('');
      setPosition('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  const nameOf = (id: number | null | undefined): string =>
    categories.find((c) => c.id === id)?.name ?? '—';

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>تصنيفات الكتالوج</h1>
        <Link className="btn btn-ghost" href="/admin">{t('admin.title')}</Link>
      </div>
      {/* G5: role="alert" عبر ErrorMsg */}
      <ErrorMsg msg={error} />

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Create category */}
        <form className="card mb-0 self-start lg:order-2" onSubmit={(e) => void create(e)}>
          <strong className="text-slate-900">تصنيف جديد</strong>
          <label className="label mt-3 block" htmlFor="cat-name">{t('common.title')}</label>
          <input id="cat-name" className="input" required maxLength={255}
            value={name} onChange={(e) => setName(e.target.value)} />

          <label className="label" htmlFor="cat-parent">التصنيف الأب (اختياري)</label>
          <select id="cat-parent" className="input" value={parentId}
            onChange={(e) => setParentId(e.target.value === '' ? '' : Number(e.target.value))}>
            <option value="">— بلا أب (تصنيف رئيسي) —</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>

          <label className="label" htmlFor="cat-position">الترتيب (اختياري)</label>
          <input id="cat-position" className="input" type="number" min={0}
            value={position} onChange={(e) => setPosition(e.target.value === '' ? '' : Math.max(0, Number(e.target.value)))} />

          <button className="btn mt-3 w-full" disabled={busy}>
            {busy ? t('common.loading') : 'إضافة التصنيف'}
          </button>
        </form>

        {/* List */}
        <div className="lg:col-span-2 lg:order-1">
          {categories.length === 0 ? (
            <div className="card text-center text-slate-500">لا توجد تصنيفات بعد.</div>
          ) : (
            <div className="card p-0">
              <ul className="divide-y divide-slate-100">
                {categories.map((c) => (
                  <li key={c.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                    <div className="min-w-0 flex-1">
                      <strong className="block truncate text-slate-900">{c.name}</strong>
                      <span className="text-xs text-slate-500" dir="ltr">{c.slug}</span>
                    </div>
                    {c.parent_id != null && (
                      <span className="badge bg-slate-100 text-slate-600">ضمن: {nameOf(c.parent_id)}</span>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
