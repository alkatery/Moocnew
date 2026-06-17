'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { API_BASE, api, ApiError, getToken } from '@/lib/api';
import type { SiteContentField } from '@/lib/types';
import { t } from '@/i18n/dictionary';
import { ErrorMsg, SuccessMsg } from '@/components/StatusMessage';

const GROUP_LABELS: Record<string, string> = {
  branding: 'الهوية والشعار',
  home: 'الصفحة الرئيسية',
  about: 'عن المنصة',
  contact: 'التواصل',
  footer: 'التذييل',
};

export default function AdminContentPage() {
  const [fields, setFields] = useState<SiteContentField[]>([]);
  const [values, setValues] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);

  async function load() {
    try {
      const res = await api<{ data: SiteContentField[] }>('/admin/site-content');
      setFields(res.data);
      setValues(Object.fromEntries(res.data.map((f) => [f.key, f.value ?? ''])));
    } catch {
      setError(t('common.error'));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void load(); }, []);

  function setVal(key: string, value: string) {
    setSaved(false);
    setValues((prev) => ({ ...prev, [key]: value }));
  }

  // Persist all text/color/url fields in one call.
  async function saveAll() {
    setBusy(true); setError(''); setSaved(false);
    try {
      const payload: Record<string, string> = {};
      for (const f of fields) {
        if (f.type !== 'image') payload[f.key] = values[f.key] ?? '';
      }
      await api('/admin/site-content', { method: 'PATCH', body: { values: payload } });
      setSaved(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t('common.error'));
    } finally {
      setBusy(false);
    }
  }

  const groups = Array.from(new Set(fields.map((f) => f.group)));

  return (
    <section>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>{t('admin.content')}</h1>
        <div className="page-actions">
          <Link className="btn btn-ghost" href="/admin">{t('admin.title')}</Link>
          <button className="btn" onClick={() => void saveAll()} disabled={busy || loading}>
            {busy ? t('common.loading') : t('common.save')}
          </button>
        </div>
      </div>

      {/* G5: role="alert"/"status" عبر ErrorMsg/SuccessMsg */}
      <ErrorMsg msg={error} />
      <SuccessMsg msg={saved ? 'تم حفظ التعديلات — ستظهر للزوّار فوراً.' : ''} />

      {loading ? (
        <p className="label">{t('common.loading')}</p>
      ) : (
        <div className="space-y-6">
          {groups.map((group) => (
            <div key={group} className="card">
              <h2 className="mb-4 border-b border-slate-100 pb-3">{GROUP_LABELS[group] ?? group}</h2>
              <div className="space-y-4">
                {fields.filter((f) => f.group === group).map((field) => (
                  <FieldEditor
                    key={field.key}
                    field={field}
                    value={values[field.key] ?? ''}
                    onChange={(v) => setVal(field.key, v)}
                    onImageChanged={(url) => {
                      setVal(field.key, url ?? '');
                      setFields((prev) => prev.map((f) => f.key === field.key ? { ...f, value: url } : f));
                    }}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

function FieldEditor({
  field,
  value,
  onChange,
  onImageChanged,
}: {
  field: SiteContentField;
  value: string;
  onChange: (v: string) => void;
  onImageChanged: (url: string | null) => void;
}) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [err, setErr] = useState('');

  async function upload(file: File) {
    setUploading(true); setErr('');
    try {
      const form = new FormData();
      form.append('image', file);
      const res = await fetch(`${API_BASE}/admin/site-content/${encodeURIComponent(field.key)}/image`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'application/json' },
        body: form,
      });
      if (!res.ok) throw new Error('upload failed');
      const json = await res.json();
      onImageChanged(json.data.value as string);
    } catch {
      setErr('تعذّر رفع الصورة. تأكد أنها صورة وبحجم أقل من 15 ميجابايت.');
    } finally {
      setUploading(false);
    }
  }

  async function clear() {
    setUploading(true); setErr('');
    try {
      await fetch(`${API_BASE}/admin/site-content/${encodeURIComponent(field.key)}/image`, {
        method: 'DELETE',
        headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'application/json' },
      });
      onImageChanged(null);
    } catch {
      setErr(t('common.error'));
    } finally {
      setUploading(false);
    }
  }

  if (field.type === 'image') {
    return (
      <div>
        <label className="label">{field.label}</label>
        <div className="mt-1.5 flex flex-wrap items-center gap-3">
          {value ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={value} alt={field.label} className="h-14 w-auto max-w-[200px] rounded-lg border border-slate-200 bg-slate-50 object-contain p-1" />
          ) : (
            <span className="flex h-14 w-24 items-center justify-center rounded-lg border border-dashed border-slate-300 text-xs text-slate-500">
              لا توجد صورة
            </span>
          )}
          <input ref={fileRef} type="file" accept="image/*,.svg" className="hidden"
            onChange={(e) => { const f = e.target.files?.[0]; if (f) void upload(f); }} />
          <button type="button" className="btn btn-ghost" disabled={uploading}
            onClick={() => fileRef.current?.click()}>
            {uploading ? t('common.loading') : value ? 'استبدال' : 'رفع صورة'}
          </button>
          {value && (
            <button type="button" className="btn btn-ghost text-red-600 ring-red-200 hover:bg-red-50"
              disabled={uploading} onClick={() => void clear()}>
              إزالة
            </button>
          )}
        </div>
        {err && <p className="error mt-2">{err}</p>}
      </div>
    );
  }

  return (
    <div>
      <label className="label" htmlFor={`f-${field.key}`}>{field.label}</label>
      {field.type === 'textarea' ? (
        <textarea id={`f-${field.key}`} className="input min-h-24"
          value={value} onChange={(e) => onChange(e.target.value)} />
      ) : field.type === 'color' ? (
        <input id={`f-${field.key}`} type="color" className="input h-11 w-20 p-1"
          value={value || '#1f3a93'} onChange={(e) => onChange(e.target.value)} />
      ) : (
        <input id={`f-${field.key}`} className="input"
          dir={field.type === 'url' ? 'ltr' : undefined}
          value={value} onChange={(e) => onChange(e.target.value)} />
      )}
    </div>
  );
}
