/**
 * اختبارات D1 — العلامات المرجعية (Bookmarks)
 * تغطّي: زر toggle (aria-pressed، تبدّل الحالة)، صفحة /bookmarks (حالات التحميل/الخطأ/الفراغ/القائمة)،
 * حذف بطاقة، روابط الدروس إلى /learn/{slug}.
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import '@testing-library/jest-dom';

// --- mock لـ Next.js Link و useParams ---
vi.mock('next/link', () => ({
  default: ({ href, children, ...rest }: { href: string; children: React.ReactNode; [k: string]: unknown }) => (
    <a href={href} {...rest}>{children}</a>
  ),
}));

vi.mock('next/navigation', () => ({
  useParams: () => ({ slug: 'test-course' }),
}));

// --- mock لمكوّنات المنصة الثقيلة (لا تُختبر هنا) ---
vi.mock('@/components/PageHeader', () => ({
  PageHeader: ({ title }: { title: string }) => <h1>{title}</h1>,
}));

vi.mock('@/components/EmptyState', () => ({
  EmptyState: ({ text, action }: { text: string; action?: React.ReactNode }) => (
    <div data-testid="empty-state">
      <p>{text}</p>
      {action}
    </div>
  ),
}));

vi.mock('@/components/LessonTypeIcon', () => ({
  LessonTypeIcon: ({ type }: { type: string }) => <span data-testid={`icon-${type}`} />,
}));

vi.mock('@/components/StatusMessage', () => ({
  ErrorMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="alert">{msg}</p> : null,
  SuccessMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="status">{msg}</p> : null,
}));

// --- mock لـ api ---
import { vi as viAlias } from 'vitest';
const mockApi = vi.fn();
vi.mock('@/lib/api', () => ({
  api: (...args: unknown[]) => mockApi(...args),
  API_BASE: 'http://localhost:8080/api/v1',
  getToken: () => 'test-token',
}));

// بيانات العلامة المرجعية النموذجية (تطابق شكل §1.أ من العقد)
const sampleBookmark = {
  id: 14,
  lesson: { id: 87, title: 'مدخل إلى الحلقات', type: 'video' as const },
  course: { id: 12, title: 'أساسيات البرمجة', slug: 'programming-basics' },
  created_at: '2026-06-10T08:30:00Z',
};

// ---------------------------------------------------------------
// مكوّن BookmarksPage
// ---------------------------------------------------------------
import BookmarksPage from '@/app/bookmarks/page';

describe('BookmarksPage — صفحة العلامات المرجعية', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('يعرض حالة التحميل أولاً', () => {
    // الـ api لا يُحلّ فوراً
    mockApi.mockReturnValue(new Promise(() => {}));
    render(<BookmarksPage />);
    expect(screen.getByText('جارٍ التحميل…')).toBeInTheDocument();
  });

  it('يعرض حالة الفراغ عند إرجاع قائمة فارغة', async () => {
    mockApi.mockResolvedValue({ data: [] });
    render(<BookmarksPage />);
    await waitFor(() =>
      expect(screen.getByTestId('empty-state')).toBeInTheDocument(),
    );
    // نص الفراغ الصحيح وفق i18n
    expect(screen.getByText(/لا علامات مرجعية بعد/)).toBeInTheDocument();
  });

  it('يعرض حالة الخطأ عند فشل الجلب', async () => {
    mockApi.mockRejectedValue(new Error('network error'));
    render(<BookmarksPage />);
    await waitFor(() =>
      // ErrorMsg يُعرض بـ role="alert"
      expect(screen.getByRole('alert')).toBeInTheDocument(),
    );
    expect(screen.getByRole('alert')).toHaveTextContent('تعذّر تحميل علاماتك المرجعية');
  });

  it('يعرض قائمة العلامات مع عنوان الدرس والمقرر', async () => {
    mockApi.mockResolvedValue({ data: [sampleBookmark] });
    render(<BookmarksPage />);
    await waitFor(() =>
      expect(screen.getByText('مدخل إلى الحلقات')).toBeInTheDocument(),
    );
    expect(screen.getByText('أساسيات البرمجة')).toBeInTheDocument();
  });

  it('رابط فتح الدرس يشير إلى /learn/{slug}', async () => {
    mockApi.mockResolvedValue({ data: [sampleBookmark] });
    render(<BookmarksPage />);
    await waitFor(() =>
      expect(screen.getByText('مدخل إلى الحلقات')).toBeInTheDocument(),
    );
    const link = screen.getByRole('link', { name: /فتح الدرس/i });
    expect(link).toHaveAttribute('href', '/learn/programming-basics');
  });

  it('الحذف يُزيل البطاقة من القائمة (إزالة محلية بعد 204)', async () => {
    // الجلب الأول يُعيد علامة، الحذف يُعيد undefined (204)
    mockApi
      .mockResolvedValueOnce({ data: [sampleBookmark] })
      .mockResolvedValueOnce(undefined);

    render(<BookmarksPage />);
    await waitFor(() =>
      expect(screen.getByText('مدخل إلى الحلقات')).toBeInTheDocument(),
    );

    const removeBtn = screen.getByRole('button', { name: /إزالة العلامة/i });
    fireEvent.click(removeBtn);

    await waitFor(() =>
      expect(screen.queryByText('مدخل إلى الحلقات')).not.toBeInTheDocument(),
    );

    // الـ api استُدعي بـ DELETE /bookmarks/14
    expect(mockApi).toHaveBeenCalledWith('/bookmarks/14', { method: 'DELETE' });
  });

  it('زر الإزالة مُعطَّل أثناء العملية (منع النقر المزدوج)', async () => {
    // الجلب الأول فوري؛ الحذف معلَّق
    mockApi
      .mockResolvedValueOnce({ data: [sampleBookmark] })
      .mockReturnValueOnce(new Promise(() => {})); // معلَّق

    render(<BookmarksPage />);
    await waitFor(() =>
      expect(screen.getByText('مدخل إلى الحلقات')).toBeInTheDocument(),
    );

    const removeBtn = screen.getByRole('button', { name: /إزالة العلامة/i });
    fireEvent.click(removeBtn);

    // الزر يجب أن يصبح مُعطَّلاً فوراً
    await waitFor(() =>
      expect(removeBtn).toBeDisabled(),
    );
  });
});

// ---------------------------------------------------------------
// مكوّن زر Toggle العلامة المرجعية (BookmarkButton مُعزول)
// نُنشئ مكوّناً وهمياً بسيطاً يحاكي منطق الزر لاختباره بمعزل
// ---------------------------------------------------------------

import { useState } from 'react';
import { t } from '@/i18n/dictionary';

/** مكوّن اختبار مُعزَّل يحاكي زر toggle في PlayerPage */
function BookmarkToggle({ initialBookmarked = false, lessonId = 87 }: {
  initialBookmarked?: boolean;
  lessonId?: number;
}) {
  const [bookmarkMap, setBookmarkMap] = useState<Map<number, number>>(
    initialBookmarked ? new Map([[lessonId, 14]]) : new Map(),
  );
  const [busy, setBusy] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [errorMsg, setErrorMsg] = useState('');

  const isBookmarked = bookmarkMap.has(lessonId);

  async function toggle() {
    if (busy) return;
    setBusy(true);
    setSuccessMsg('');
    setErrorMsg('');
    const existingId = bookmarkMap.get(lessonId);
    try {
      if (existingId !== undefined) {
        await mockApi(`/bookmarks/${existingId}`, { method: 'DELETE' });
        setBookmarkMap((prev) => { const n = new Map(prev); n.delete(lessonId); return n; });
        setSuccessMsg(t('lesson.bookmark'));
      } else {
        const res = await mockApi('/bookmarks', { method: 'POST', body: { lesson_id: lessonId } }) as { data: { id: number } };
        setBookmarkMap((prev) => new Map(prev).set(lessonId, res.data.id));
        setSuccessMsg(t('lesson.bookmarked'));
      }
    } catch {
      setErrorMsg(t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      {errorMsg && <p role="alert">{errorMsg}</p>}
      {successMsg && <p role="status">{successMsg}</p>}
      <button
        aria-pressed={isBookmarked}
        aria-label={isBookmarked ? t('bookmarks.remove') : t('bookmarks.add')}
        disabled={busy}
        onClick={() => void toggle()}
        data-testid="bookmark-toggle"
      >
        {isBookmarked ? t('bookmarks.remove') : t('bookmarks.add')}
      </button>
    </div>
  );
}

describe('زر Toggle العلامة المرجعية', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('aria-pressed=false عندما لا توجد علامة', () => {
    render(<BookmarkToggle initialBookmarked={false} />);
    const btn = screen.getByTestId('bookmark-toggle');
    expect(btn).toHaveAttribute('aria-pressed', 'false');
    expect(btn).toHaveAccessibleName(t('bookmarks.add'));
  });

  it('aria-pressed=true عند وجود علامة', () => {
    render(<BookmarkToggle initialBookmarked={true} />);
    const btn = screen.getByTestId('bookmark-toggle');
    expect(btn).toHaveAttribute('aria-pressed', 'true');
    expect(btn).toHaveAccessibleName(t('bookmarks.remove'));
  });

  it('POST عند النقر على زر غير محفوظ → aria-pressed يصبح true', async () => {
    mockApi.mockResolvedValue({ data: { id: 99 } });
    render(<BookmarkToggle initialBookmarked={false} />);

    const btn = screen.getByTestId('bookmark-toggle');
    expect(btn).toHaveAttribute('aria-pressed', 'false');

    fireEvent.click(btn);

    await waitFor(() =>
      expect(btn).toHaveAttribute('aria-pressed', 'true'),
    );

    expect(mockApi).toHaveBeenCalledWith('/bookmarks', {
      method: 'POST',
      body: { lesson_id: 87 },
    });
  });

  it('DELETE عند النقر على زر محفوظ → aria-pressed يصبح false', async () => {
    mockApi.mockResolvedValue(undefined); // 204
    render(<BookmarkToggle initialBookmarked={true} />);

    const btn = screen.getByTestId('bookmark-toggle');
    expect(btn).toHaveAttribute('aria-pressed', 'true');

    fireEvent.click(btn);

    await waitFor(() =>
      expect(btn).toHaveAttribute('aria-pressed', 'false'),
    );

    expect(mockApi).toHaveBeenCalledWith('/bookmarks/14', { method: 'DELETE' });
  });

  it('يعرض رسالة نجاح (role=status) بعد الإضافة', async () => {
    mockApi.mockResolvedValue({ data: { id: 99 } });
    render(<BookmarkToggle initialBookmarked={false} />);

    fireEvent.click(screen.getByTestId('bookmark-toggle'));

    await waitFor(() =>
      expect(screen.getByRole('status')).toBeInTheDocument(),
    );
  });

  it('يعرض رسالة خطأ (role=alert) عند فشل الطلب', async () => {
    mockApi.mockRejectedValue(new Error('server error'));
    render(<BookmarkToggle initialBookmarked={false} />);

    fireEvent.click(screen.getByTestId('bookmark-toggle'));

    await waitFor(() =>
      expect(screen.getByRole('alert')).toBeInTheDocument(),
    );
    expect(screen.getByRole('alert')).toHaveTextContent('حدث خطأ.');
  });

  it('الزر مُعطَّل أثناء الطلب المعلَّق (منع التكرار)', async () => {
    mockApi.mockReturnValue(new Promise(() => {})); // معلَّق
    render(<BookmarkToggle initialBookmarked={false} />);

    const btn = screen.getByTestId('bookmark-toggle');
    fireEvent.click(btn);

    await waitFor(() => expect(btn).toBeDisabled());
  });
});
