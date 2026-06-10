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
        <SiteContentProvider>
          <AuthProvider>
            <ImpersonationBanner />
            <Nav />
            <main className="container flex-1">{children}</main>
            <Footer />
          </AuthProvider>
        </SiteContentProvider>
      </body>
    </html>
  );
}
