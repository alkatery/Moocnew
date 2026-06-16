/**
 * اختبارات E2 — جدولة ظهور الأقسام (SectionScheduler)
 *
 * تغطّي وفق معايير القبول §5 (الأمامي) من عقد E2:
 *  - قسم visible_from = null → شارة «ظاهر»
 *  - قسم visible_from مستقبلي → شارة «يظهر في {تاريخ}»
 *  - قسم visible_from ماضٍ → شارة «ظاهر» (اعتبر ظاهراً)
 *  - زرّ الجدولة يفتح حقل التاريخ + label مرتبط (a11y)
 *  - حفظ قيمة → PATCH بـ visible_from ISO صالح
 *  - حفظ فارغ → PATCH بـ null (إلغاء الجدولة)
 *  - زرّ «إلغاء الجدولة» يستدعي PATCH بـ null
 *  - نجاح الحفظ → SuccessMsg + استدعاء onSaved
 *  - فشل API → ErrorMsg (role=alert)
 *  - a11y: aria-label، aria-expanded، label/htmlFor
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import '@testing-library/jest-dom';

// --- mock لـ api من lib ---
const mockApi = vi.fn();
vi.mock('@/lib/api', () => ({
  api: (...args: unknown[]) => mockApi(...args),
}));

// --- mock مكوّنات الحالة ---
vi.mock('@/components/StatusMessage', () => ({
  ErrorMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="alert">{msg}</p> : null,
  SuccessMsg: ({ msg }: { msg: string }) =>
    msg ? <p role="status">{msg}</p> : null,
}));

// --- mock formatDate لإخراج متوقع ---
vi.mock('@/lib/format', () => ({
  formatDate: (iso: string | null | undefined) =>
    iso ? `تاريخ-${iso.slice(0, 10)}` : '',
}));

import { SectionScheduler } from '@/components/studio/SectionScheduler';
import type { Section } from '@/lib/types';

// قسم بلا جدولة
const sectionNull: Section = {
  id: 10,
  title: 'مقدّمة',
  position: 1,
  lessons: [],
  visible_from: null,
};

// قسم بجدولة مستقبلية (2099)
const sectionFuture: Section = {
  id: 11,
  title: 'الأسبوع الثاني',
  position: 2,
  lessons: [],
  visible_from: '2099-07-01T09:00:00+00:00',
};

// قسم بتاريخ ماضٍ (اعتبر ظاهراً)
const sectionPast: Section = {
  id: 12,
  title: 'الأسبوع الثالث',
  position: 3,
  lessons: [],
  visible_from: '2000-01-01T00:00:00+00:00',
};

describe('SectionScheduler — E2', () => {
  const onSaved = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- شارات الحالة ----

  it('يعرض شارة «ظاهر» عند visible_from = null', () => {
    render(<SectionScheduler section={sectionNull} onSaved={onSaved} />);
    expect(screen.getByText('ظاهر')).toBeInTheDocument();
    expect(screen.queryByText(/يظهر في/)).not.toBeInTheDocument();
  });

  it('يعرض شارة «يظهر في {تاريخ}» عند visible_from مستقبلي', () => {
    render(<SectionScheduler section={sectionFuture} onSaved={onSaved} />);
    expect(screen.getByText(/يظهر في/)).toBeInTheDocument();
    // يتضمّن التاريخ المُنسَّق
    expect(screen.getByText(/تاريخ-2099-07-01/)).toBeInTheDocument();
  });

  it('يعرض شارة «ظاهر» عند visible_from في الماضي (اعتبر ظاهراً)', () => {
    render(<SectionScheduler section={sectionPast} onSaved={onSaved} />);
    expect(screen.getByText('ظاهر')).toBeInTheDocument();
  });

  // ---- a11y: فتح المحرر ----

  it('زرّ الجدولة يحمل aria-label ويفتح المحرر عند الضغط', () => {
    render(<SectionScheduler section={sectionNull} onSaved={onSaved} />);
    const btn = screen.getByRole('button', { name: /جدولة الظهور.*مقدّمة/i });
    expect(btn).toHaveAttribute('aria-expanded', 'false');
    fireEvent.click(btn);
    expect(btn).toHaveAttribute('aria-expanded', 'true');
    // يظهر حقل التاريخ
    expect(screen.getByLabelText('تاريخ ووقت ظهور القسم')).toBeInTheDocument();
  });

  it('حقل التاريخ مرتبط بـ label (htmlFor/id)', () => {
    render(<SectionScheduler section={sectionNull} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: /جدولة الظهور/i }));
    // getByLabelText يتحقّق من ارتباط label/id
    const input = screen.getByLabelText('تاريخ ووقت ظهور القسم');
    expect(input).toHaveAttribute('type', 'datetime-local');
  });

  // ---- PATCH بـ visible_from ----

  it('حفظ تاريخ صالح يستدعي PATCH بـ ISO string ثم يستدعي onSaved', async () => {
    mockApi.mockResolvedValueOnce({});
    render(<SectionScheduler section={sectionNull} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: /جدولة الظهور/i }));

    const input = screen.getByLabelText('تاريخ ووقت ظهور القسم');
    // نضبط قيمة datetime-local
    fireEvent.change(input, { target: { value: '2099-08-15T10:00' } });

    fireEvent.submit(input.closest('form')!);

    await waitFor(() => {
      expect(mockApi).toHaveBeenCalledWith(
        '/catalog/sections/10',
        expect.objectContaining({
          method: 'PATCH',
          body: expect.objectContaining({
            // القيمة ISO — نتحقق فقط من البنية
            visible_from: expect.stringMatching(/^\d{4}-\d{2}-\d{2}T/),
          }),
        }),
      );
    });

    await waitFor(() => {
      expect(onSaved).toHaveBeenCalledOnce();
    });
  });

  it('نجاح الحفظ يعرض SuccessMsg (role=status)', async () => {
    mockApi.mockResolvedValueOnce({});
    render(<SectionScheduler section={sectionNull} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: /جدولة الظهور/i }));
    const input = screen.getByLabelText('تاريخ ووقت ظهور القسم');
    fireEvent.change(input, { target: { value: '2099-08-15T10:00' } });
    fireEvent.submit(input.closest('form')!);

    await waitFor(() => {
      expect(screen.getByRole('status')).toBeInTheDocument();
    });
  });

  // ---- PATCH بـ null (حقل فارغ) ----

  it('حفظ حقل فارغ يستدعي PATCH بـ { visible_from: null }', async () => {
    mockApi.mockResolvedValueOnce({});
    render(<SectionScheduler section={sectionFuture} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: /جدولة الظهور/i }));

    const input = screen.getByLabelText('تاريخ ووقت ظهور القسم');
    // إفراغ الحقل
    fireEvent.change(input, { target: { value: '' } });
    fireEvent.submit(input.closest('form')!);

    await waitFor(() => {
      expect(mockApi).toHaveBeenCalledWith(
        '/catalog/sections/11',
        expect.objectContaining({
          method: 'PATCH',
          body: { visible_from: null },
        }),
      );
    });
  });

  // ---- زرّ «إلغاء الجدولة» ----

  it('زرّ «إلغاء الجدولة» يظهر فقط عند وجود visible_from ويستدعي PATCH بـ null', async () => {
    mockApi.mockResolvedValueOnce({});
    render(<SectionScheduler section={sectionFuture} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: /جدولة الظهور/i }));

    // زرّ إلغاء الجدولة يجب أن يكون حاضراً
    const clearBtn = screen.getByRole('button', {
      name: /إلغاء الجدولة.*الأسبوع الثاني/i,
    });
    expect(clearBtn).toBeInTheDocument();

    fireEvent.click(clearBtn);

    await waitFor(() => {
      expect(mockApi).toHaveBeenCalledWith(
        '/catalog/sections/11',
        expect.objectContaining({
          method: 'PATCH',
          body: { visible_from: null },
        }),
      );
      expect(onSaved).toHaveBeenCalledOnce();
    });
  });

  it('زرّ «إلغاء الجدولة» لا يظهر عند visible_from = null', () => {
    render(<SectionScheduler section={sectionNull} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: /جدولة الظهور/i }));
    expect(
      screen.queryByRole('button', { name: /إلغاء الجدولة/i }),
    ).not.toBeInTheDocument();
  });

  // ---- معالجة الخطأ ----

  it('فشل API يعرض ErrorMsg (role=alert) ولا يستدعي onSaved', async () => {
    mockApi.mockRejectedValueOnce(new Error('network error'));
    render(<SectionScheduler section={sectionNull} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: /جدولة الظهور/i }));
    const input = screen.getByLabelText('تاريخ ووقت ظهور القسم');
    fireEvent.change(input, { target: { value: '2099-08-15T10:00' } });
    fireEvent.submit(input.closest('form')!);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
    expect(onSaved).not.toHaveBeenCalled();
  });
});
