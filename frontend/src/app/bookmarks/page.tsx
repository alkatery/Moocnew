'use client';

// صفحة علاماتي المرجعية — D1
// تعرض قائمة العلامات المرجعية للمستخدم مع إمكانية الحذف والانتقال للدرس.

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import type { Bookmark } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorMsg } from '@/components/StatusMessage';
import { LessonTypeIcon } from '@/components/LessonTypeIcon';

// تسمية نوع الدرس بالعربية
const TYPE_LABELS: Record<string, string> = {
  video: 'فيديو',
  article: 'مقالة',
  image: 'مصوَّر',
  file: 'ملف',
  live: 'مباشر',
};

export default function BookmarksPage() {
  const [items, setItems] = useState<Bookmark[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  // تتبع أزرار الحذف المعلّقة لمنع النقر المزدوج
  const [removing, setRemoving] = useState<Set<number>>(new Set());

  useEffect(() => {
    api<{ data: Bookmark[] }>('/bookmarks')
      .then((r) => setItems(r.data))
      .catch(() => setError(t('bookmarks.error')))
      .finally(() => setLoading(false));
  }, []);

  // حذف علامة مرجعية بعد تأكيد الـ 204
  async function removeBookmark(id: number) {
    setError('');
    setRemoving((prev) => new Set(prev).add(id));
    try {
      await api(`/bookmarks/${id}`, { method: 'DELETE' });
      // إزالة فورية من القائمة المحلية
      setItems((prev) => prev.filter((b) => b.id !== id));
    } catch {
      setError(t('common.error'));
    } finally {
      setRemoving((prev) => {
        const next = new Set(prev);
        next.delete(id);
        return next;
      });
    }
  }

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader
        title={t('bookmarks.title')}
        subtitle="الدروس التي حفظتها للرجوع إليها بسرعة."
        crumbs={[{ label: t('bookmarks.title') }]}
      />

      {/* G5: رسائل الخطأ عبر role="alert" */}
      <ErrorMsg msg={error} />

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : items.length === 0 ? (
        <EmptyState
          text={t('bookmarks.empty')}
          action={
            <Link className="btn" href="/learn">
              {t('nav.myLearning')}
            </Link>
          }
        />
      ) : (
        <div className="card p-0">
          <ul className="divide-y divide-slate-100">
            {items.map((bookmark) => (
              <li
                key={bookmark.id}
                className="flex flex-wrap items-center gap-3 px-5 py-4"
              >
                {/* أيقونة نوع الدرس */}
                <span
                  className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"
                  aria-hidden
                >
                  <LessonTypeIcon type={bookmark.lesson.type} />
                </span>

                {/* بيانات الدرس والمقرر */}
                <div className="min-w-0 flex-1">
                  <strong className="block text-slate-900">
                    {bookmark.lesson.title}
                  </strong>
                  <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span className="badge">
                      {TYPE_LABELS[bookmark.lesson.type] ?? bookmark.lesson.type}
                    </span>
                    <span>{bookmark.course.title}</span>
                  </div>
                </div>

                {/* أزرار الإجراءات */}
                <div className="page-actions shrink-0">
                  {/* رابط فتح الدرس في مشغّل المقرر */}
                  <Link
                    className="btn btn-ghost"
                    href={`/learn/${bookmark.course.slug}`}
                    aria-label={`${t('bookmarks.open')}: ${bookmark.lesson.title}`}
                  >
                    {t('bookmarks.open')}
                  </Link>

                  {/* زر حذف العلامة المرجعية */}
                  <button
                    className="btn btn-ghost text-red-600 hover:bg-red-50 hover:text-red-700"
                    onClick={() => void removeBookmark(bookmark.id)}
                    disabled={removing.has(bookmark.id)}
                    aria-label={`${t('bookmarks.remove')}: ${bookmark.lesson.title}`}
                  >
                    {removing.has(bookmark.id) ? t('common.loading') : t('bookmarks.remove')}
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
