import Link from 'next/link';
import { t } from '@/i18n/dictionary';

export default function HomePage() {
  return (
    <section className="overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-700 to-brand-500 px-8 py-16 text-center text-white shadow-card">
      <h1 className="text-4xl font-extrabold text-white">{t('app.name')}</h1>
      <p className="mx-auto mt-4 max-w-xl text-brand-100">
        تعلَّم من نخبة المدرّسين — دورات تفاعلية بالفيديو والاختبارات والشهادات، تعمل بالكامل مجاناً.
      </p>
      <div className="mt-8 flex justify-center gap-3">
        <Link className="btn bg-white text-brand-700 hover:bg-brand-50" href="/catalog">{t('nav.catalog')}</Link>
        <Link className="btn btn-ghost border border-white/40 text-white hover:bg-white/10" href="/register">{t('nav.register')}</Link>
      </div>
    </section>
  );
}
