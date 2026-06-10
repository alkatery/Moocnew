import type { MetadataRoute } from 'next';

const SITE = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000';

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: '*',
      allow: '/',
      // Keep private/authenticated areas out of the index.
      disallow: ['/admin', '/studio', '/learn', '/orders', '/notifications', '/plans', '/certificates'],
    },
    sitemap: `${SITE}/sitemap.xml`,
  };
}
