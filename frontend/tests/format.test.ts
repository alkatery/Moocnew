import { describe, it, expect } from 'vitest';
import { formatCount, formatDate, formatMinor } from '@/lib/format';

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

describe('formatDate', () => {
  it('formats an ISO date (en locale)', () => {
    expect(formatDate('2026-06-10T08:00:00Z', 'en-US')).toContain('2026');
  });
  it('returns an empty string for null/undefined', () => {
    expect(formatDate(null)).toBe('');
    expect(formatDate(undefined)).toBe('');
  });
});

describe('formatCount', () => {
  it('compacts thousands (en locale)', () => {
    expect(formatCount(12500, 'en-US')).toBe('12.5K');
  });
  it('keeps small numbers as-is', () => {
    expect(formatCount(7, 'en-US')).toBe('7');
  });
});
