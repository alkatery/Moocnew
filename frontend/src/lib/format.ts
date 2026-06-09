// Money is stored in integer minor units on the backend (never float).
export function formatMinor(minor: number, currency = 'SAR', locale = 'ar-SA'): string {
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(minor / 100);
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`;
  }
}
