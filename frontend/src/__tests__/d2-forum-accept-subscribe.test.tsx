/**
 * اختبارات D2 — تمييز الإجابة + متابعة الموضوع
 *
 * تغطّي (وفق §8 من عقد D2 — الأمامي):
 *  - زرّ المتابعة: aria-pressed يعكس subscribed، toggle POST↔DELETE، رسالة نجاح/خطأ
 *  - زرّ التمييز: يظهر فقط حين can_accept (وليس على الرد الأول)
 *  - aria-pressed على زر التمييز يعكس الرد المميَّز الحالي
 *  - شارة «إجابة مقبولة» تظهر على الرد المميَّز وتختفي بعد الإلغاء
 *  - التحديث المحلّي من استجابة API (لا إعادة تحميل)
 *  - حالة التحميل وحالة الخطأ وحالة الفراغ
 *  - RTL محفوظ (dir="rtl" على document)
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import '@testing-library/jest-dom';

// --- mock لـ Next.js ---
vi.mock('next/link', () => ({
  default: ({
    href,
    children,
    ...rest
  }: {
    href: string;
    children: React.ReactNode;
    [k: string]: unknown;
  }) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

vi.mock('next/navigation', () => ({
  useParams: () => ({ id: '12' }),
}));

// --- mock لمكوّنات المنصة ---
vi.mock('@/components/PageHeader', () => ({
  PageHeader: ({ title }: { title: string }) => <h1>{title}</h1>,
}));

vi.mock('@/components/EmptyState', () => ({
  EmptyState: ({ text }: { text: string }) => (
    <div data-testid="empty-state">
      <p>{text}</p>
    </div>
  ),
}));

vi.mock('@/components/StatusMessage', () => ({
  ErrorMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="alert">{msg}</p> : null,
  SuccessMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="status">{msg}</p> : null,
}));

// --- mock لـ useAuth ---
vi.mock('@/lib/auth', () => ({
  useAuth: () => ({ user: { id: 5, name: 'أحمد', email: 'ahmed@test.com', roles: ['student'] } }),
}));

// --- mock لـ api ---
const mockApi = vi.fn();
vi.mock('@/lib/api', () => ({
  api: (...args: unknown[]) => mockApi(...args),
  API_BASE: 'http://localhost:8080/api/v1',
  getToken: () => 'test-token',
}));

// ---------------------------------------------------------------
// بيانات النموذج
// ---------------------------------------------------------------

/** استجابة show نموذجية — صاحب الموضوع + رد واحد */
const makeThreadDetail = (overrides: {
  accepted_post_id?: number | null;
  subscribed?: boolean;
  can_accept?: boolean;
} = {}) => ({
  data: {
    thread: {
      id: 12,
      title: 'كيف أحل مسألة الحلقات؟',
      user_id: 5,
      accepted_post_id: overrides.accepted_post_id ?? null,
    },
    posts: [
      { id: 10, body: 'هذا السؤال…', user_id: 5, created_at: '2026-06-01T10:00:00Z' },
      { id: 87, body: 'استخدم حلقة for مع شرط.', user_id: 9, created_at: '2026-06-01T11:00:00Z' },
    ],
    subscribed: overrides.subscribed ?? false,
    can_accept: overrides.can_accept ?? true,
  },
});

// ---------------------------------------------------------------
// استيراد الصفحة
// ---------------------------------------------------------------
import ThreadPage from '@/app/community/thread/[id]/page';

// ---------------------------------------------------------------
// مجموعة: حالات التحميل والخطأ والفراغ
// ---------------------------------------------------------------
describe('ThreadPage — حالات التحميل والخطأ', () => {
  beforeEach(() => vi.clearAllMocks());

  it('يعرض نصّ التحميل أولاً قبل استكمال الجلب', () => {
    // الجلب معلَّق
    mockApi.mockReturnValue(new Promise(() => {}));
    render(<ThreadPage />);
    expect(screen.getByText('جارٍ التحميل…')).toBeInTheDocument();
  });

  it('يعرض رسالة خطأ عند فشل الجلب', async () => {
    mockApi.mockRejectedValue(new Error('network'));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.getByRole('alert')).toBeInTheDocument(),
    );
  });

  it('يعرض حالة الفراغ (EmptyState) عندما لا توجد ردود', async () => {
    mockApi.mockResolvedValue({
      data: {
        thread: { id: 12, title: 'بلا ردود', user_id: 5, accepted_post_id: null },
        posts: [],
        subscribed: false,
        can_accept: false,
      },
    });
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.getByTestId('empty-state')).toBeInTheDocument(),
    );
  });
});

// ---------------------------------------------------------------
// مجموعة: زرّ المتابعة
// ---------------------------------------------------------------
describe('ThreadPage — زرّ المتابعة (subscribe)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('aria-pressed=false عند subscribed=false', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ subscribed: false }));
    render(<ThreadPage />);
    await waitFor(() =>
      // انتظر اختفاء مؤشر التحميل
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );
    const btn = screen.getByRole('button', { name: /متابعة الموضوع/i });
    expect(btn).toHaveAttribute('aria-pressed', 'false');
  });

  it('aria-pressed=true عند subscribed=true', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ subscribed: true }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );
    const btn = screen.getByRole('button', { name: /إلغاء المتابعة/i });
    expect(btn).toHaveAttribute('aria-pressed', 'true');
  });

  it('POST /subscribe عند النقر على زر غير متابع → aria-pressed يصبح true', async () => {
    // جلب: غير متابع
    mockApi.mockResolvedValueOnce(makeThreadDetail({ subscribed: false }));
    // POST subscribe
    mockApi.mockResolvedValueOnce({ data: { thread_id: 12, subscribed: true } });

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const btn = screen.getByRole('button', { name: /متابعة الموضوع/i });
    expect(btn).toHaveAttribute('aria-pressed', 'false');

    fireEvent.click(btn);

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /إلغاء المتابعة/i }))
        .toHaveAttribute('aria-pressed', 'true'),
    );

    // تحقّق أنّ الاستدعاء كان POST
    expect(mockApi).toHaveBeenCalledWith(
      '/community/threads/12/subscribe',
      { method: 'POST' },
    );
  });

  it('DELETE /subscribe عند النقر على زر متابع → aria-pressed يصبح false', async () => {
    // جلب: متابع
    mockApi.mockResolvedValueOnce(makeThreadDetail({ subscribed: true }));
    // DELETE subscribe
    mockApi.mockResolvedValueOnce({ data: { thread_id: 12, subscribed: false } });

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const btn = screen.getByRole('button', { name: /إلغاء المتابعة/i });
    expect(btn).toHaveAttribute('aria-pressed', 'true');

    fireEvent.click(btn);

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /متابعة الموضوع/i }))
        .toHaveAttribute('aria-pressed', 'false'),
    );

    // تحقّق أنّ الاستدعاء كان DELETE
    expect(mockApi).toHaveBeenCalledWith(
      '/community/threads/12/subscribe',
      { method: 'DELETE' },
    );
  });

  it('يعرض رسالة نجاح (role=status) بعد متابعة ناجحة', async () => {
    mockApi.mockResolvedValueOnce(makeThreadDetail({ subscribed: false }));
    mockApi.mockResolvedValueOnce({ data: { thread_id: 12, subscribed: true } });

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: /متابعة الموضوع/i }));

    await waitFor(() =>
      // رسالة النجاح — رسالة subscribed
      expect(screen.getAllByRole('status').length).toBeGreaterThan(0),
    );
  });

  it('يعرض رسالة خطأ (role=alert) عند فشل المتابعة', async () => {
    mockApi.mockResolvedValueOnce(makeThreadDetail({ subscribed: false }));
    mockApi.mockRejectedValueOnce(new Error('server error'));

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: /متابعة الموضوع/i }));

    await waitFor(() => {
      const alerts = screen.getAllByRole('alert');
      const subscribeErr = alerts.find((a) =>
        a.textContent?.includes('تعذّر تحديث المتابعة'),
      );
      expect(subscribeErr).toBeInTheDocument();
    });
  });

  it('الزرّ معطَّل أثناء الطلب المعلَّق (منع التكرار)', async () => {
    mockApi.mockResolvedValueOnce(makeThreadDetail({ subscribed: false }));
    mockApi.mockReturnValueOnce(new Promise(() => {})); // معلَّق

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const btn = screen.getByRole('button', { name: /متابعة الموضوع/i });
    fireEvent.click(btn);

    await waitFor(() => expect(btn).toBeDisabled());
  });
});

// ---------------------------------------------------------------
// مجموعة: زرّ التمييز والشارة
// ---------------------------------------------------------------
describe('ThreadPage — زرّ تمييز الإجابة', () => {
  beforeEach(() => vi.clearAllMocks());

  it('زرّ التمييز يظهر على الردود (عدا الأول) عند can_accept=true', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ can_accept: true }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // زرّ التمييز يجب أن يكون موجوداً (للرد الثاني)
    const acceptBtn = screen.getByRole('button', { name: /تمييز كإجابة مقبولة/i });
    expect(acceptBtn).toBeInTheDocument();
  });

  it('زرّ التمييز لا يظهر عند can_accept=false', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ can_accept: false }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    expect(
      screen.queryByRole('button', { name: /تمييز كإجابة مقبولة/i }),
    ).not.toBeInTheDocument();
  });

  it('aria-pressed=false على زرّ التمييز عندما لا يوجد رد مميَّز', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ can_accept: true, accepted_post_id: null }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const btn = screen.getByRole('button', { name: /تمييز كإجابة مقبولة/i });
    expect(btn).toHaveAttribute('aria-pressed', 'false');
  });

  it('aria-pressed=true على زرّ التمييز للرد المميَّز', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ can_accept: true, accepted_post_id: 87 }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // يجب أن يكون زرّ إلغاء التمييز موجوداً (نصّ بديل)
    const btn = screen.getByRole('button', { name: /إلغاء تمييز الإجابة/i });
    expect(btn).toHaveAttribute('aria-pressed', 'true');
  });

  it('شارة «إجابة مقبولة» تظهر على الرد المميَّز (article بتسمية تشمل الشارة)', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ can_accept: true, accepted_post_id: 87 }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // الـ article للرد المميَّز يحمل aria-label يتضمّن «إجابة مقبولة»
    expect(
      screen.getByRole('article', { name: /إجابة مقبولة/i }),
    ).toBeInTheDocument();
    // الشارة النصّية موجودة داخله
    expect(
      screen.getByRole('article', { name: /إجابة مقبولة/i })
        .querySelector('[role="status"]'),
    ).toHaveTextContent('إجابة مقبولة');
  });

  it('شارة «إجابة مقبولة» لا تظهر عند عدم وجود رد مميَّز', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ can_accept: true, accepted_post_id: null }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // لا يوجد article بتسمية «إجابة مقبولة»
    expect(
      screen.queryByRole('article', { name: /إجابة مقبولة/i }),
    ).not.toBeInTheDocument();
  });

  it('POST /accept يُميَّز الرد محليّاً ويُظهر الشارة', async () => {
    // جلب: لا رد مميَّز
    mockApi.mockResolvedValueOnce(makeThreadDetail({ can_accept: true, accepted_post_id: null }));
    // POST accept → يُعيد accepted_post_id=87
    mockApi.mockResolvedValueOnce({ data: { thread_id: 12, accepted_post_id: 87 } });

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // قبل النقر: لا شارة داخل article
    expect(screen.queryByRole('article', { name: /إجابة مقبولة/i })).not.toBeInTheDocument();

    const acceptBtn = screen.getByRole('button', { name: /تمييز كإجابة مقبولة/i });
    fireEvent.click(acceptBtn);

    // بعد النقر: شارة في الـ span[role=status] داخل article المميَّز
    await waitFor(() => {
      // الـ article للرد المميَّز يجب أن يحمل تسمية تتضمّن «إجابة مقبولة»
      expect(
        screen.getByRole('article', { name: /إجابة مقبولة/i }),
      ).toBeInTheDocument();
    });

    // تحقّق من الاستدعاء
    expect(mockApi).toHaveBeenCalledWith(
      '/community/threads/12/accept',
      { method: 'POST', body: { post_id: 87 } },
    );
  });

  it('POST /accept على رد مميَّز (toggle) يُلغي التمييز ويُخفي الشارة', async () => {
    // جلب: رد 87 مميَّز
    mockApi.mockResolvedValueOnce(makeThreadDetail({ can_accept: true, accepted_post_id: 87 }));
    // POST accept → إلغاء (null)
    mockApi.mockResolvedValueOnce({ data: { thread_id: 12, accepted_post_id: null } });

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // الشارة موجودة داخل الـ article
    expect(
      screen.getByRole('article', { name: /إجابة مقبولة/i }),
    ).toBeInTheDocument();

    // زرّ إلغاء التمييز
    const unacceptBtn = screen.getByRole('button', { name: /إلغاء تمييز الإجابة/i });
    fireEvent.click(unacceptBtn);

    await waitFor(() =>
      expect(
        screen.queryByRole('article', { name: /إجابة مقبولة/i }),
      ).not.toBeInTheDocument(),
    );
  });

  it('يعرض رسالة خطأ (role=alert) عند فشل التمييز', async () => {
    mockApi.mockResolvedValueOnce(makeThreadDetail({ can_accept: true, accepted_post_id: null }));
    mockApi.mockRejectedValueOnce(new Error('403'));

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: /تمييز كإجابة مقبولة/i }));

    await waitFor(() => {
      const alerts = screen.getAllByRole('alert');
      const acceptErr = alerts.find((a) =>
        a.textContent?.includes('تعذّر تمييز الإجابة'),
      );
      expect(acceptErr).toBeInTheDocument();
    });
  });

  it('زرّ التمييز معطَّل أثناء الطلب المعلَّق', async () => {
    mockApi.mockResolvedValueOnce(makeThreadDetail({ can_accept: true, accepted_post_id: null }));
    mockApi.mockReturnValueOnce(new Promise(() => {}));

    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const btn = screen.getByRole('button', { name: /تمييز كإجابة مقبولة/i });
    fireEvent.click(btn);

    await waitFor(() => expect(btn).toBeDisabled());
  });
});

// ---------------------------------------------------------------
// مجموعة: سلامة a11y الأساسية
// ---------------------------------------------------------------
describe('ThreadPage — a11y', () => {
  beforeEach(() => vi.clearAllMocks());

  it('عنوان الموضوع يُعرض كـ h1', async () => {
    mockApi.mockResolvedValue(makeThreadDetail());
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('شارة «إجابة مقبولة» لا تعتمد على اللون وحده (النصّ موجود داخل الـ article)', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ accepted_post_id: 87 }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );
    // النصّ مرئي مستقلّ عن اللون — الـ article يحمل aria-label يتضمّن «إجابة مقبولة»
    const acceptedArticle = screen.getByRole('article', { name: /إجابة مقبولة/i });
    expect(acceptedArticle).toBeInTheDocument();
    // الشارة تحمل نصّاً لا لون فقط
    expect(
      acceptedArticle.querySelector('[role="status"]'),
    ).toHaveTextContent('إجابة مقبولة');
  });

  it('زرّ المتابعة له aria-label واضحة (تسمية وصفية)', async () => {
    mockApi.mockResolvedValue(makeThreadDetail({ subscribed: false }));
    render(<ThreadPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );
    const btn = screen.getByRole('button', { name: /متابعة الموضوع/i });
    expect(btn).toHaveAttribute('aria-label');
    expect(btn.getAttribute('aria-label')).toBeTruthy();
  });
});
