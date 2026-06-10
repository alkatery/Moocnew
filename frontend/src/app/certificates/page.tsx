'use client';

import { useEffect, useState } from 'react';
import { API_BASE, getToken } from '@/lib/api';
import { api } from '@/lib/api';
import type { CertificateView } from '@/lib/types';
import { formatDate } from '@/lib/format';
import { t } from '@/i18n/dictionary';
import { PageHeader } from '@/components/PageHeader';
import { EmptyState } from '@/components/EmptyState';

export default function CertificatesPage() {
  const [items, setItems] = useState<CertificateView[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ data: CertificateView[] }>('/certificates')
      .then((r) => setItems(r.data)).catch(() => setItems([])).finally(() => setLoading(false));
  }, []);

  // The PDF endpoint needs the bearer token, so fetch as a blob and save.
  async function download(cert: CertificateView) {
    setError('');
    try {
      const res = await fetch(`${API_BASE}/certificates/${cert.verification_uuid}/download`, {
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      if (!res.ok) throw new Error('download failed');
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `certificate-${cert.serial}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError(t('common.error'));
    }
  }

  return (
    <section className="mx-auto max-w-3xl">
      <PageHeader
        title={t('certificates.title')}
        subtitle="كل شهاداتك الموثّقة — لكل شهادة رمز QR وصفحة تحقق علنية."
        crumbs={[{ label: t('certificates.title') }]}
      />
      {error && <p className="error mb-4">{error}</p>}

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : items.length === 0 ? (
        <EmptyState text={t('certificates.empty')} />
      ) : (
        <div className="card p-0">
          <ul className="divide-y divide-slate-100">
            {items.map((cert) => (
              <li key={cert.verification_uuid} className="flex flex-wrap items-center gap-3 px-5 py-4">
                <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${cert.subject_type === 'learning_path' ? 'bg-amber-50 text-amber-600' : 'bg-brand-50 text-brand-700'}`}>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-3 1.5L8 21l4-2 4 2-1-5.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                </span>
                <div className="min-w-0 flex-1">
                  <strong className="block text-slate-900">{cert.course_title}</strong>
                  <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                    <span className="badge">
                      {cert.subject_type === 'learning_path' ? t('certificates.path') : t('certificates.course')}
                    </span>
                    <span dir="ltr">{cert.serial}</span>
                    <time>{formatDate(cert.issued_at)}</time>
                  </div>
                </div>
                <button className="btn btn-ghost shrink-0" onClick={() => void download(cert)}>
                  {t('certificates.download')}
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
