import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';
import { CourseCard } from '@/components/CourseCard';
import type { Course } from '@/lib/types';

// G7 (B3-wcag-audit): فحص a11y آلي مُدمج في مجموعة الاختبارات.
// يصيّر مكوّنات حقيقية ويؤكّد خلوّها من مخالفات WCAG التي يكشفها axe-core.
expect.extend(toHaveNoViolations);

const course: Course = {
  id: 1,
  title: 'أساسيات تطوير الويب',
  slug: 'web-basics',
  summary: 'مقدّمة عملية لبناء الويب',
  description: 'دورة تغطّي HTML وCSS وJavaScript من الصفر.',
  status: 'published',
  pricing_type: 'free',
  price_minor: 0,
  rating: 4.6,
  reviews_count: 32,
  instructor: { id: 7, name: 'سارة محمد' },
};

describe('a11y (axe)', () => {
  it('StatusMessage: ErrorMsg has role=alert and no violations', async () => {
    const { container, getByRole } = render(<ErrorMsg msg="حدث خطأ ما" />);
    expect(getByRole('alert').textContent).toContain('حدث خطأ ما');
    expect(await axe(container)).toHaveNoViolations();
  });

  it('StatusMessage: SuccessMsg has role=status and no violations', async () => {
    const { container, getByRole } = render(<SuccessMsg msg="تم الحفظ" />);
    expect(getByRole('status').textContent).toContain('تم الحفظ');
    expect(await axe(container)).toHaveNoViolations();
  });

  it('CourseCard renders with no accessibility violations', async () => {
    // الكروت روابط تُصيَّر داخل قائمة دلالية — يلفّها axe ضمن <main> صحيح المعالم.
    const { container } = render(
      <main>
        <ul>
          <li>
            <CourseCard course={course} index={0} />
          </li>
        </ul>
      </main>,
    );
    expect(await axe(container)).toHaveNoViolations();
  });

  it('a labelled form pattern passes axe', async () => {
    const { container } = render(
      <main>
        <form aria-label="نموذج تجريبي">
          <label htmlFor="a11y-email">البريد الإلكتروني</label>
          <input id="a11y-email" type="email" dir="ltr" />
          <button type="submit">إرسال</button>
        </form>
      </main>,
    );
    expect(await axe(container)).toHaveNoViolations();
  });
});
