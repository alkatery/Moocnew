'use client';

/** Read-only or interactive 1–5 star rating. */
export function Stars({
  value,
  size = 16,
  onChange,
}: {
  value: number;
  size?: number;
  onChange?: (v: number) => void;
}) {
  return (
    <span className="inline-flex items-center gap-0.5" dir="ltr">
      {[1, 2, 3, 4, 5].map((i) => (
        <button
          key={i}
          type="button"
          disabled={!onChange}
          onClick={() => onChange?.(i)}
          className={onChange ? 'cursor-pointer' : 'cursor-default'}
          aria-label={`${i}`}
        >
          <svg width={size} height={size} viewBox="0 0 24 24"
            fill={i <= Math.round(value) ? '#f59e0b' : 'none'}
            stroke={i <= Math.round(value) ? '#f59e0b' : '#cbd5e1'} strokeWidth="1.5">
            <path d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 18.3 6.2 21l1.1-6.5L2.6 9.8l6.5-.9L12 3Z" strokeLinejoin="round" />
          </svg>
        </button>
      ))}
    </span>
  );
}
