import { describe, it, expect } from 'vitest';
import { formatMinor } from '@/lib/format';

describe('formatMinor', () => {
  it('renders minor units as a currency amount (en locale)', () => {
    expect(formatMinor(49900, 'SAR', 'en-US')).toContain('499');
  });
  it('handles zero', () => {
    expect(formatMinor(0, 'SAR', 'en-US')).toContain('0');
  });
  it('divides minor units by 100', () => {
    // 12345 minor => 123.45
    expect(formatMinor(12345, 'SAR', 'en-US')).toContain('123.45');
  });
});
