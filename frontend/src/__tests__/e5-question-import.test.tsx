/**
 * اختبارات E5 — استيراد الأسئلة من مكتبة المؤلّف (QuestionImportPicker).
 *
 * تغطّي معايير القبول §6.3 من عقد E5:
 *  16. حالات تحميل/فراغ/خطأ/نجاح — أدوار a11y صحيحة
 *  17. زرّ «استيراد المحدّد» معطّل عند صفر اختيار؛ يستدعي POST import بالمعرّفات؛ بعد النجاح يستدعي onImported
 *  18. عند importable فارغ → رسالة الفراغ، لا قائمة
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import '@testing-library/jest-dom';

// --- mock لـ api ---
const mockApi = vi.fn();
vi.mock('@/lib/api', () => ({
  api: (...args: unknown[]) => mockApi(...args),
}));

// --- mock StatusMessage ---
vi.mock('@/components/StatusMessage', () => ({
  ErrorMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="alert">{msg}</p> : null,
  SuccessMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="status">{msg}</p> : null,
}));

import { QuestionImportPicker } from '@/components/studio/QuestionImportPicker';

// -------------------------------------------------------------------
// بيانات تجريبية

const sampleQuestions = [
  {
    id: 101,
    type: 'mcq' as const,
    body: 'ما عاصمة المملكة العربية السعودية؟',
    points: 2,
    choices_count: 4,
    source_course: { id: 10, title: 'مقرر الجغرافيا', slug: 'geography' },
  },
  {
    id: 102,
    type: 'true_false' as const,
    body: 'الأرض كروية الشكل.',
    points: 1,
    choices_count: null,
    source_course: { id: 10, title: 'مقرر الجغرافيا', slug: 'geography' },
  },
  {
    id: 103,
    type: 'numerical' as const,
    body: 'كم عدد كواكب المجموعة الشمسية؟',
    points: 3,
    choices_count: null,
    source_course: { id: 11, title: 'مقرر العلوم', slug: 'science' },
  },
];

const defaultProps = {
  courseSlug: 'target-course',
  onImported: vi.fn(),
  onClose: vi.fn(),
};

// -------------------------------------------------------------------

describe('QuestionImportPicker — E5', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- §16 — حالة التحميل ----

  it('يُظهر مؤشّر التحميل أثناء الجلب (role=status)', async () => {
    // نؤخّر الاستجابة كي يظهر loading
    let resolve!: (v: unknown) => void;
    mockApi.mockReturnValueOnce(new Promise((r) => { resolve = r; }));

    render(<QuestionImportPicker {...defaultProps} />);

    expect(screen.getByRole('status')).toBeInTheDocument();
    expect(screen.getByRole('status').textContent).toMatch(/جارٍ/);

    // نحلّ الوعد ثم ننتظر استقرار الحالة داخل نطاق الاختبار (منع تسرّب تحديث async للاختبار التالي).
    resolve({ data: [] });
    await waitFor(() => expect(screen.queryByText(/جارٍ/)).not.toBeInTheDocument());
  });

  // ---- §18 — حالة الفراغ ----

  it('يُظهر رسالة الفراغ عند إرجاع قائمة فارغة (لا قائمة/checkboxes)', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.queryByRole('status', { name: /جارٍ/ })).not.toBeInTheDocument();
    });

    // رسالة الفراغ حاضرة
    expect(screen.getByRole('status')).toBeInTheDocument();
    expect(screen.getByRole('status').textContent).toMatch(/لا أسئلة متاحة/);

    // لا checkboxes لأسئلة
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  });

  // ---- §16 — حالة الخطأ ----

  it('يُظهر ErrorMsg (role=alert) عند فشل الجلب', async () => {
    mockApi.mockRejectedValueOnce(new Error('network error'));

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
    expect(screen.getByRole('alert').textContent).toMatch(/تعذّر تحميل/);
  });

  // ---- §16 + §17 — عرض الأسئلة ----

  it('يُظهر الأسئلة مع checkbox معنونة لكل سؤال', async () => {
    mockApi.mockResolvedValueOnce({ data: sampleQuestions });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    // 3 checkboxes للأسئلة + 1 لـ«تحديد الكل»
    const checkboxes = screen.getAllByRole('checkbox');
    expect(checkboxes.length).toBe(4);

    // كل checkbox للسؤال معنون
    expect(screen.getByLabelText(/ما عاصمة المملكة/)).toBeInTheDocument();
  });

  // ---- §17 — زرّ الاستيراد معطّل عند صفر اختيار ----

  it('زرّ «استيراد المحدّد» معطّل عند عدم تحديد أسئلة', async () => {
    mockApi.mockResolvedValueOnce({ data: sampleQuestions });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    const importBtn = screen.getByRole('button', { name: /استيراد المحدّد/ });
    expect(importBtn).toBeDisabled();
  });

  // ---- §17 — تحديد سؤال يُفعّل الزرّ ----

  it('تحديد سؤال يُفعّل زرّ الاستيراد', async () => {
    mockApi.mockResolvedValueOnce({ data: sampleQuestions });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    // تحديد أول سؤال
    const checkbox = screen.getByLabelText(/ما عاصمة المملكة/);
    fireEvent.click(checkbox);

    const importBtn = screen.getByRole('button', { name: /استيراد المحدّد/ });
    expect(importBtn).not.toBeDisabled();
  });

  // ---- §17 — استدعاء POST import بالمعرّفات الصحيحة ----

  it('يستدعي POST import بمعرّفات الأسئلة المختارة', async () => {
    mockApi
      .mockResolvedValueOnce({ data: sampleQuestions }) // جلب importable
      .mockResolvedValueOnce({ data: [] });             // POST import

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    // تحديد سؤالَين
    fireEvent.click(screen.getByLabelText(/ما عاصمة المملكة/));
    fireEvent.click(screen.getByLabelText(/الأرض كروية/));

    fireEvent.click(screen.getByRole('button', { name: /استيراد المحدّد/ }));

    await waitFor(() => {
      expect(mockApi).toHaveBeenCalledWith(
        '/assessment/courses/target-course/questions/import',
        expect.objectContaining({
          method: 'POST',
          body: expect.objectContaining({
            source_question_ids: expect.arrayContaining([101, 102]),
          }),
        }),
      );
    });
  });

  // ---- §17 — استدعاء onImported بعد النجاح ----

  it('يستدعي onImported بعد نجاح الاستيراد', async () => {
    const onImported = vi.fn();
    mockApi
      .mockResolvedValueOnce({ data: sampleQuestions })
      .mockResolvedValueOnce({ data: [] });

    render(
      <QuestionImportPicker
        courseSlug="target-course"
        onImported={onImported}
        onClose={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByLabelText(/ما عاصمة المملكة/));
    fireEvent.click(screen.getByRole('button', { name: /استيراد المحدّد/ }));

    await waitFor(() => {
      expect(onImported).toHaveBeenCalledOnce();
    });
  });

  // ---- §16 — رسالة النجاح بعد الاستيراد ----

  it('يُظهر SuccessMsg (role=status) بعد نجاح الاستيراد', async () => {
    mockApi
      .mockResolvedValueOnce({ data: sampleQuestions })
      .mockResolvedValueOnce({ data: [] });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByLabelText(/ما عاصمة المملكة/));
    fireEvent.click(screen.getByRole('button', { name: /استيراد المحدّد/ }));

    await waitFor(() => {
      expect(screen.getByRole('status')).toBeInTheDocument();
      expect(screen.getByRole('status').textContent).toMatch(/استُورد/);
    });
  });

  // ---- §16 — ErrorMsg عند فشل الاستيراد ----

  it('يُظهر ErrorMsg (role=alert) عند فشل POST import', async () => {
    mockApi
      .mockResolvedValueOnce({ data: sampleQuestions })
      .mockRejectedValueOnce(new Error('server error'));

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByLabelText(/ما عاصمة المملكة/));
    fireEvent.click(screen.getByRole('button', { name: /استيراد المحدّد/ }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
      expect(screen.getByRole('alert').textContent).toMatch(/تعذّر الاستيراد/);
    });
  });

  // ---- عرض اسم المقرر المصدر كشارة ----

  it('يُظهر اسم المقرر المصدر لكل سؤال', async () => {
    mockApi.mockResolvedValueOnce({ data: sampleQuestions });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getAllByText('مقرر الجغرافيا').length).toBeGreaterThanOrEqual(1);
    });
    // قد يوجد أكثر من عنصر بنفس النص (شارة + select)
    expect(screen.getAllByText('مقرر العلوم').length).toBeGreaterThanOrEqual(1);
  });

  // ---- a11y: حقل البحث معنون ----

  it('حقل البحث معنون بـ label مرتبط (a11y)', async () => {
    mockApi.mockResolvedValueOnce({ data: [] });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      const input = screen.getByRole('searchbox');
      expect(input).toBeInTheDocument();
      // يحمل aria-label أو مرتبط بـ label
      expect(input).toHaveAttribute('aria-label');
    });
  });

  // ---- a11y: fieldset/legend لقائمة الأسئلة ----

  it('قائمة الأسئلة داخل fieldset مع legend (a11y)', async () => {
    mockApi.mockResolvedValueOnce({ data: sampleQuestions });

    const { container } = render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    expect(container.querySelector('fieldset')).toBeInTheDocument();
    expect(container.querySelector('legend')).toBeInTheDocument();
  });

  // ---- تحديد الكل ----

  it('checkbox «تحديد الكل» يُحدِّد جميع الأسئلة', async () => {
    mockApi.mockResolvedValueOnce({ data: sampleQuestions });

    render(<QuestionImportPicker {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('ما عاصمة المملكة العربية السعودية؟')).toBeInTheDocument();
    });

    // checkbox «تحديد الكل»
    const selectAllCheckbox = screen.getByLabelText('تحديد كل الأسئلة');
    fireEvent.click(selectAllCheckbox);

    // الزرّ يعرض العدد الكامل
    expect(
      screen.getByRole('button', { name: /استيراد المحدّد \(3\)/ }),
    ).not.toBeDisabled();
  });

  // ---- زرّ إغلاق المنتقي ----

  it('زرّ الإغلاق (✕) يستدعي onClose', async () => {
    const onClose = vi.fn();
    mockApi.mockResolvedValueOnce({ data: [] });

    render(
      <QuestionImportPicker
        courseSlug="target-course"
        onImported={vi.fn()}
        onClose={onClose}
      />,
    );

    await waitFor(() => {
      // انتظار انتهاء التحميل
      expect(screen.queryByRole('status', { name: /جارٍ/ })).not.toBeInTheDocument();
    });

    const closeBtn = screen.getByRole('button', { name: /إغلاق/ });
    fireEvent.click(closeBtn);
    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---- AssessmentsPanel: زرّ «استيراد من مكتبتي» يفتح المنتقي ----

  it('AssessmentsPanel: زرّ «استيراد من مكتبتي» يفتح منتقي الاستيراد', async () => {
    // mock جلب بنك الأسئلة والاختبارات والواجبات
    mockApi.mockResolvedValue({ data: [] });

    const { AssessmentsPanel } = await import(
      '@/components/studio/AssessmentsPanel'
    );

    render(<AssessmentsPanel courseSlug="c" sections={[]} />);

    const openBtn = await screen.findByRole('button', {
      name: 'استيراد من مكتبتي',
    });
    expect(openBtn).toBeInTheDocument();
    expect(openBtn).toHaveAttribute('aria-expanded', 'false');

    fireEvent.click(openBtn);
    expect(openBtn).toHaveAttribute('aria-expanded', 'true');

    // المنتقي ظاهر الآن (يجلب importable)
    await waitFor(() => {
      expect(screen.getByText('استيراد أسئلة من مقرراتك الأخرى')).toBeInTheDocument();
    });
  });
});
