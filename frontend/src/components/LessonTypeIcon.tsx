const PATHS: Record<string, React.ReactNode> = {
  video: <path d="M5 5h14v14H5zM10 9l5 3-5 3z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />,
  article: <path d="M6 3h12v18H6zM9 8h6M9 12h6M9 16h4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />,
  image: <path d="M4 5h16v14H4zM4 15l4-4 4 4 4-5 4 5M9 9.5a.8.8 0 1 0 0-1.6.8.8 0 0 0 0 1.6z" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />,
  file: <path d="M6 3h8l4 4v14H6zM14 3v4h4" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />,
  live: <path d="M12 12m-2 0a2 2 0 1 0 4 0 2 2 0 1 0-4 0M7.5 7.5a7 7 0 0 0 0 9m9-9a7 7 0 0 1 0 9M4.7 4.7a11 11 0 0 0 0 14.6m14.6-14.6a11 11 0 0 1 0 14.6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />,
  // نشاط «تفكير ومشاركة» — فقاعة حوار.
  activity: <path d="M4 5h16v11H9l-4 4v-4H4zM8 9h8M8 12h5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />,
};

export function LessonTypeIcon({ type, size = 16 }: { type: string; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden>
      {PATHS[type] ?? PATHS.article}
    </svg>
  );
}
