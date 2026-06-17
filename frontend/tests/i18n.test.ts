import { describe, it, expect } from 'vitest';
import { t } from '@/i18n/dictionary';

describe('i18n', () => {
  it('defaults to Arabic', () => {
    expect(t('nav.catalog')).toBe('الدورات');
  });
  it('falls back to English when requested', () => {
    expect(t('nav.catalog', 'en')).toBe('Courses');
  });
});
