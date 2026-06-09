import Link from 'next/link';
import { t } from '@/i18n/dictionary';

export default function HomePage() {
  return (
    <section>
      <h1>{t('app.name')}</h1>
      <p className="label">منصة تعليم جماهيري مفتوح — تصفّح الدورات وابدأ التعلّم مجاناً.</p>
      <Link className="btn" href="/catalog">{t('nav.catalog')}</Link>
    </section>
  );
}
