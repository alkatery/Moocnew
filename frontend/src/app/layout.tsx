import type { Metadata } from 'next';
import './globals.css';
import { AuthProvider } from '@/lib/auth';
import { SiteContentProvider } from '@/lib/siteContent';
import { Nav } from '@/components/Nav';
import { Footer } from '@/components/Footer';
import { ImpersonationBanner } from '@/components/ImpersonationBanner';

export const metadata: Metadata = {
  title: 'منصة MOOC — تعلّم مهارات المستقبل بالعربية',
  description:
    'منصة تعليم جماهيري مفتوح: دورات فيديو تفاعلية، اختبارات وواجبات، شهادات موثّقة برمز QR، جلسات مباشرة ومجتمع نقاش — بالعربية وللجميع.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar" dir="rtl">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
          href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap"
          rel="stylesheet"
        />
      </head>
      <body className="flex min-h-screen flex-col">
        {/* G1: رابط تخطّي للمحتوى الرئيسي — مرئي عند التركيز بلوحة المفاتيح فقط */}
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4
                     focus:z-50 focus:rounded-xl focus:bg-brand-600 focus:px-4 focus:py-2
                     focus:text-sm focus:font-bold focus:text-white focus:outline-none
                     focus:ring-2 focus:ring-white"
        >
          تخطّى إلى المحتوى الرئيسي
        </a>
        <SiteContentProvider>
          <AuthProvider>
            <ImpersonationBanner />
            <Nav />
            {/* G1: id="main-content" هدف رابط التخطّي */}
            <main id="main-content" className="container flex-1">{children}</main>
            <Footer />
          </AuthProvider>
        </SiteContentProvider>
      </body>
    </html>
  );
}
