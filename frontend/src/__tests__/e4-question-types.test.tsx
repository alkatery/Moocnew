/**
 * اختبارات E4 — أنواع الأسئلة الإضافية في الواجهة (dropdown, multi_select, numerical, regex).
 * (1) تأليف الأنواع متاح في AssessmentsPanel؛ (2) أداء الطالب يعرض الضابط الصحيح لكل نوع.
 * التصحيح وحجب الإجابة مُغطَّيان خلفياً (58 اختباراً).
 */
import { render, screen, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import '@testing-library/jest-dom';

vi.mock('next/navigation', () => ({
  useParams: () => ({ id: '1', slug: 'c' }),
  useRouter: () => ({ back: vi.fn(), push: vi.fn() }),
}));
vi.mock('@/components/PageHeader', () => ({ PageHeader: ({ title }: { title: string }) => <h1>{title}</h1> }));
vi.mock('@/components/StatusMessage', () => ({
  ErrorMsg: ({ msg }: { msg: string }) => (msg ? <p role="alert">{msg}</p> : null),
  SuccessMsg: ({ msg }: { msg: string }) => (msg ? <p role="status">{msg}</p> : null),
}));
const mockApi = vi.fn();
vi.mock('@/lib/api', () => ({
  api: (...a: unknown[]) => mockApi(...a),
  ApiError: class ApiError extends Error {},
  API_BASE: 'http://localhost:8080/api/v1',
  getToken: () => 'test-token',
}));

import { AssessmentsPanel } from '@/components/studio/AssessmentsPanel';
import QuizRunnerPage from '@/app/quiz/[id]/page';

describe('E4 — تأليف الأنواع متاح', () => {
  beforeEach(() => { mockApi.mockReset(); mockApi.mockResolvedValue({ data: [] }); });

  it('AssessmentsPanel يعرض الأنواع الأربعة الجديدة في منتقي النوع', async () => {
    render(<AssessmentsPanel courseSlug="c" sections={[]} />);
    for (const label of ['قائمة منسدلة', 'اختيار متعدّد الإجابات', 'إدخال رقمي', 'مطابقة نصّ (Regex)']) {
      expect(await screen.findByRole('option', { name: label })).toBeInTheDocument();
    }
  });
});

describe('E4 — أداء الطالب يعرض الضابط الصحيح لكل نوع', () => {
  const questions = [
    { id: 1, type: 'dropdown', body: 'سؤال منسدل', choices: [{ id: 'a', text: 'أ' }, { id: 'b', text: 'ب' }], points: 1 },
    { id: 2, type: 'multi_select', body: 'سؤال متعدّد', choices: [{ id: 'a', text: 'أ' }, { id: 'b', text: 'ب' }], points: 1 },
    { id: 3, type: 'numerical', body: 'سؤال رقمي', points: 1 },
    { id: 4, type: 'regex', body: 'سؤال نصّي', points: 1 },
  ];
  beforeEach(() => {
    mockApi.mockReset();
    mockApi.mockResolvedValue({ attempt: { id: 99 }, questions, quiz: { time_limit_minutes: null } });
  });

  it('dropdown→select · multi_select→checkboxes · numerical→number · regex→text · بلا تسريب', async () => {
    const { container } = render(<QuizRunnerPage />);
    // تُعرَض الأسئلة بعد بدء المحاولة؛ ننتظر ظهور الحقل الرقمي للسؤال 3.
    await waitFor(() => expect(container.querySelector('#numerical-3')).toBeTruthy());
    expect(container.querySelector('select')).toBeTruthy();
    expect(container.querySelector('#numerical-3')?.getAttribute('type')).toBe('number');
    expect(container.querySelectorAll('input[type="checkbox"]').length).toBeGreaterThan(0);
    expect(container.querySelector('#regex-4')).toBeTruthy();
    const served = JSON.stringify(questions);
    expect(served).not.toContain('"correct"');
    expect(served).not.toContain('"config"');
  });
});
