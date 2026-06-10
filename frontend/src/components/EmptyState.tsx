export function EmptyState({ text, action }: { text: string; action?: React.ReactNode }) {
  return (
    <div className="card flex flex-col items-center py-12 text-center">
      <span className="icon-tile mb-3 h-14 w-14 rounded-2xl">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="M4 19V5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Zm10-16v6h6" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" />
        </svg>
      </span>
      <p className="text-slate-500">{text}</p>
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
