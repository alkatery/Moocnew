// Money is stored in integer minor units on the backend (never float).
export function formatMinor(minor: number, currency = 'SAR', locale = 'ar-SA'): string {
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(minor / 100);
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`;
  }
}

export function formatDate(iso: string | null | undefined, locale = 'ar'): string {
  if (!iso) return '';
  try {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(new Date(iso));
  } catch {
    return iso.slice(0, 10);
  }
}

// Compact counter for marketing stats (e.g. 12500 → "12.5K").
export function formatCount(value: number, locale = 'ar'): string {
  try {
    return new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 }).format(value);
  } catch {
    return String(value);
  }
}
