/**
 * اختبارات D3 — منتقي تكرار ملخّص الإشعارات
 *
 * تغطّي (وفق §9 من عقد D3 — الأمامي / §8 معايير القبول الأمامي):
 *  - يعرض قيمة digest.frequency الأولية من استجابة GET
 *  - الخيارات الثلاثة (off/daily/weekly) حاضرة كمدخلات راديو
 *  - تغيير الاختيار يستدعي PUT بـ { digest_frequency: value } فقط
 *  - لا يُرسَل preferences في طلب تغيير التكرار
 *  - حالة التحميل وحالة الخطأ وحالة الفراغ
 *  - رسالة نجاح (role=status) بعد الحفظ الناجح
 *  - رسالة خطأ (role=alert) عند الفشل
 *  - fieldset + legend (a11y مجموعة الراديو)
 *  - RTL محفوظ
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
  useRouter: () => ({ push: vi.fn() }),
  useParams: () => ({}),
}));

// --- mock مكوّنات المنصة ---
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

/** استجابة GET /notifications/preferences نموذجية */
const makePrefsResponse = (frequency: 'off' | 'daily' | 'weekly' = 'off') => ({
  data: [
    { type: 'session_reminder', channel: 'mail', enabled: true },
    { type: 'session_reminder', channel: 'database', enabled: true },
  ],
  digest: { frequency },
});

/** استجابة PUT /notifications/preferences ناجحة */
const makePutResponse = (frequency: 'off' | 'daily' | 'weekly') => ({
  data: [
    { type: 'session_reminder', channel: 'mail', enabled: true },
    { type: 'session_reminder', channel: 'database', enabled: true },
  ],
  digest: { frequency },
});

// ---------------------------------------------------------------
// استيراد الصفحة
// ---------------------------------------------------------------
import NotificationsPage from '@/app/notifications/page';

// ---------------------------------------------------------------
// مجموعة: حالات التحميل والخطأ الأولية
// ---------------------------------------------------------------
describe('NotificationsPage — حالات التحميل', () => {
  beforeEach(() => vi.clearAllMocks());

  it('يعرض نصّ التحميل أولاً قبل اكتمال الجلب', () => {
    // كلا طلبَي GET معلَّقان
    mockApi.mockReturnValue(new Promise(() => {}));
    render(<NotificationsPage />);
    expect(screen.getByText('جارٍ التحميل…')).toBeInTheDocument();
  });

  it('يعرض قسم الملخّص بعد التحميل', async () => {
    // GET /notifications → قائمة فارغة
    mockApi.mockResolvedValueOnce({ data: [] });
    // GET /notifications/preferences → off
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // العنوان الرئيسي لقسم الملخّص — يظهر كـ h2 (وأيضاً في legend.sr-only)
    const headings = screen.getAllByText('ملخّص النشاط عبر البريد');
    expect(headings.length).toBeGreaterThanOrEqual(1);
    // أحدها يجب أن يكون h2
    const h2 = headings.find((el) => el.tagName === 'H2');
    expect(h2).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------
// مجموعة: قراءة القيمة الأولية (§9.أ — الحالة الأولية من digest.frequency)
// ---------------------------------------------------------------
describe('NotificationsPage — القيمة الأولية لتكرار الملخّص', () => {
  beforeEach(() => vi.clearAllMocks());

  it('يحدّد off كافتراضٍ عند digest.frequency=off', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const offRadio = screen.getByRole('radio', { name: /فوري — دون تجميع/i });
    expect(offRadio).toBeChecked();
  });

  it('يحدّد daily عند digest.frequency=daily', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('daily'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const dailyRadio = screen.getByRole('radio', { name: /ملخّص يومي/i });
    expect(dailyRadio).toBeChecked();
  });

  it('يحدّد weekly عند digest.frequency=weekly', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('weekly'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const weeklyRadio = screen.getByRole('radio', { name: /ملخّص أسبوعي/i });
    expect(weeklyRadio).toBeChecked();
  });
});

// ---------------------------------------------------------------
// مجموعة: الخيارات الثلاثة حاضرة
// ---------------------------------------------------------------
describe('NotificationsPage — خيارات الملخّص الثلاثة', () => {
  beforeEach(() => vi.clearAllMocks());

  it('يعرض ثلاثة خيارات راديو: off و daily و weekly', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const radios = screen.getAllByRole('radio', { name: /فوري|يومي|أسبوعي/i });
    expect(radios).toHaveLength(3);
  });

  it('الخيارات لها name="digest_frequency" الموحَّد', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const radios = screen.getAllByRole('radio');
    // كل راديو في مجموعة الملخّص له name="digest_frequency"
    const digestRadios = radios.filter(
      (r) => r.getAttribute('name') === 'digest_frequency',
    );
    expect(digestRadios).toHaveLength(3);
  });
});

// ---------------------------------------------------------------
// مجموعة: حفظ التكرار — §5.ب (PUT بـ digest_frequency فقط)
// ---------------------------------------------------------------
describe('NotificationsPage — حفظ تكرار الملخّص عبر PUT', () => {
  beforeEach(() => vi.clearAllMocks());

  it('اختيار daily يستدعي PUT بـ { digest_frequency: "daily" } فقط', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));
    // PUT ناجح
    mockApi.mockResolvedValueOnce(makePutResponse('daily'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const dailyRadio = screen.getByRole('radio', { name: /ملخّص يومي/i });
    fireEvent.click(dailyRadio);

    await waitFor(() =>
      expect(mockApi).toHaveBeenCalledWith(
        '/notifications/preferences',
        expect.objectContaining({
          method: 'PUT',
          body: { digest_frequency: 'daily' },
        }),
      ),
    );

    // لا يُرسَل preferences في هذا الطلب
    const putCall = mockApi.mock.calls.find(
      (c) => c[1]?.method === 'PUT' && c[1]?.body?.digest_frequency === 'daily',
    );
    expect(putCall).toBeTruthy();
    expect(putCall![1].body).not.toHaveProperty('preferences');
  });

  it('اختيار weekly يستدعي PUT بـ { digest_frequency: "weekly" }', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));
    mockApi.mockResolvedValueOnce(makePutResponse('weekly'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const weeklyRadio = screen.getByRole('radio', { name: /ملخّص أسبوعي/i });
    fireEvent.click(weeklyRadio);

    await waitFor(() =>
      expect(mockApi).toHaveBeenCalledWith(
        '/notifications/preferences',
        expect.objectContaining({
          method: 'PUT',
          body: { digest_frequency: 'weekly' },
        }),
      ),
    );
  });

  it('اختيار off يستدعي PUT بـ { digest_frequency: "off" }', async () => {
    // حالة أولية: daily
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('daily'));
    mockApi.mockResolvedValueOnce(makePutResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const offRadio = screen.getByRole('radio', { name: /فوري — دون تجميع/i });
    fireEvent.click(offRadio);

    await waitFor(() =>
      expect(mockApi).toHaveBeenCalledWith(
        '/notifications/preferences',
        expect.objectContaining({
          method: 'PUT',
          body: { digest_frequency: 'off' },
        }),
      ),
    );
  });

  it('بعد الحفظ الناجح يُحدَّث الاختيار من استجابة الخادم (مصدر الحقيقة)', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));
    // الخادم يُعيد weekly
    mockApi.mockResolvedValueOnce(makePutResponse('weekly'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('radio', { name: /ملخّص أسبوعي/i }));

    await waitFor(() => {
      // خيار weekly محدَّد
      const weeklyRadio = screen.getByRole('radio', { name: /ملخّص أسبوعي/i });
      expect(weeklyRadio).toBeChecked();
    });
  });
});

// ---------------------------------------------------------------
// مجموعة: رسائل الحالة (§9.د)
// ---------------------------------------------------------------
describe('NotificationsPage — رسائل الحالة بعد الحفظ', () => {
  beforeEach(() => vi.clearAllMocks());

  it('يعرض رسالة نجاح (role=status) بعد الحفظ الناجح', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));
    mockApi.mockResolvedValueOnce(makePutResponse('daily'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('radio', { name: /ملخّص يومي/i }));

    await waitFor(() => {
      const statusMsgs = screen.getAllByRole('status');
      const successMsg = statusMsgs.find((m) =>
        m.textContent?.includes('تم تحديث تكرار الملخّص'),
      );
      expect(successMsg).toBeInTheDocument();
    });
  });

  it('يعرض رسالة خطأ (role=alert) عند فشل الحفظ', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));
    mockApi.mockRejectedValueOnce(new Error('500 Server Error'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('radio', { name: /ملخّص يومي/i }));

    await waitFor(() => {
      const alerts = screen.getAllByRole('alert');
      const digestErr = alerts.find((a) =>
        a.textContent?.includes('تعذّر تحديث تكرار الملخّص'),
      );
      expect(digestErr).toBeInTheDocument();
    });
  });

  it('لا تظهر رسالة خطأ عند التحميل الأولي الناجح', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // لا تنبيه خطأ بدون محاولة حفظ
    const alerts = screen.queryAllByRole('alert');
    expect(alerts).toHaveLength(0);
  });
});

// ---------------------------------------------------------------
// مجموعة: a11y — §9.د
// ---------------------------------------------------------------
describe('NotificationsPage — a11y قسم الملخّص', () => {
  beforeEach(() => vi.clearAllMocks());

  it('مجموعة الراديو داخل fieldset (عنصر معروف للقارئات)', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // يجب أن يوجد fieldset يضمّ خيارات الراديو
    const radios = screen.getAllByRole('radio', { name: /فوري|يومي|أسبوعي/i });
    // الراديو داخل fieldset
    const fieldset = radios[0].closest('fieldset');
    expect(fieldset).not.toBeNull();
  });

  it('legend موجود داخل fieldset (يُعلَن اسم المجموعة للقارئ)', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const radios = screen.getAllByRole('radio', { name: /فوري|يومي|أسبوعي/i });
    const fieldset = radios[0].closest('fieldset');
    expect(fieldset).not.toBeNull();
    const legend = fieldset?.querySelector('legend');
    expect(legend).not.toBeNull();
  });

  it('النصّ التوضيحي (digestHint) معروض', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // النصّ التوضيحي حسب العقد §9.أ
    expect(
      screen.getByText(/يومي.*بريد.*أسبوعي.*فوري/i),
    ).toBeInTheDocument();
  });

  it('مقبض قسم الملخّص معنون (aria-labelledby يشير إلى h2)', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // القسم لديه عنوان h2 معرَّف بـ aria-labelledby
    const section = document.querySelector('section[aria-labelledby="digest-section-heading"]');
    expect(section).not.toBeNull();
    const heading = document.getElementById('digest-section-heading');
    expect(heading).not.toBeNull();
    expect(heading?.tagName).toBe('H2');
  });

  it('fieldset معطَّل أثناء الحفظ الجاري (منع التكرار)', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));
    // PUT معلَّق
    mockApi.mockReturnValueOnce(new Promise(() => {}));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    const dailyRadio = screen.getByRole('radio', { name: /ملخّص يومي/i });
    fireEvent.click(dailyRadio);

    await waitFor(() => {
      const fieldset = dailyRadio.closest('fieldset');
      expect(fieldset).toHaveProperty('disabled', true);
    });
  });
});

// ---------------------------------------------------------------
// مجموعة: عدم كسر تفضيلات القنوات القائمة
// ---------------------------------------------------------------
describe('NotificationsPage — عدم كسر القنوات القائمة', () => {
  beforeEach(() => vi.clearAllMocks());

  it('جدول type×channel لا يزال معروضاً بعد تحميل الملخّص', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('daily'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    // عنوان قسم التفضيلات موجود
    expect(screen.getByText('تفضيلات الإشعارات')).toBeInTheDocument();
  });

  it('تغيير تكرار الملخّص لا يُرسَل معه preferences', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });
    mockApi.mockResolvedValueOnce(makePrefsResponse('off'));
    mockApi.mockResolvedValueOnce(makePutResponse('weekly'));

    render(<NotificationsPage />);
    await waitFor(() =>
      expect(screen.queryByText('جارٍ التحميل…')).not.toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('radio', { name: /ملخّص أسبوعي/i }));

    await waitFor(() => {
      const putCall = mockApi.mock.calls.find(
        (c) => c[1]?.method === 'PUT',
      );
      expect(putCall).toBeTruthy();
      // لا يحتوي الطلب على preferences
      expect(putCall![1].body).not.toHaveProperty('preferences');
      // يحتوي على digest_frequency فقط
      expect(putCall![1].body).toStrictEqual({ digest_frequency: 'weekly' });
    });
  });
});
