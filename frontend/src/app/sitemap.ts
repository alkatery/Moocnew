import type { MetadataRoute } from 'next';
import { API_BASE } from '@/lib/api';

const SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000';

interface Listed { slug: string; published_at?: string | null }

async function fetchSlugs(path: string): Promise<Listed[]> {
  try {
    const res = await fetch(`${API_BASE}${path}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) return [];
    const json = await res.json();
    return (json.data ?? []) as Listed[];
  } catch {
    return [];
  }
}

// Dynamically include published courses, paths and news so search engines
// can discover every public page.
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const statics = ['', '/catalog', '/paths', '/news', '/about', '/contact', '/leaderboard'].map((p) => ({
    url: `${SITE}${p}`,
    changeFrequency: 'weekly' as const,
    priority: p === '' ? 1 : 0.7,
  }));

  const [courses, paths, news] = await Promise.all([
    fetchSlugs('/catalog/courses?per_page=50'),
    fetchSlugs('/learning/paths'),
    fetchSlugs('/content/news?per_page=50'),
  ]);

  const dynamic = [
    ...courses.map((c) => ({ url: `${SITE}/catalog/${c.slug}`, priority: 0.8 })),
    ...paths.map((p) => ({ url: `${SITE}/paths/${p.slug}`, priority: 0.8 })),
    ...news.map((n) => ({ url: `${SITE}/news/${n.slug}`, priority: 0.5 })),
  ];

  return [...statics, ...dynamic];
}
