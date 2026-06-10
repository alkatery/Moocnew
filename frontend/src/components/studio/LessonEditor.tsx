'use client';

import { useEffect, useState } from 'react';
import { API_BASE, api, getToken } from '@/lib/api';
import type { LessonAuthoring, LessonKind } from '@/lib/types';
import { t } from '@/i18n/dictionary';

const TYPE_LABELS: Record<LessonKind, string> = {
  video: 'فيديو',
  article: 'مقال نصي',
  image: 'صورة',
  file: 'ملف (PDF…)',
  live: 'جلسة مباشرة',
};

/**
 * Full inline editor for a single lesson inside the studio curriculum:
 * type, rich text content, image/PDF asset upload, video source,
 * transcript (manual + provider auto-transcription) and free preview.
 */
export function LessonEditor({
  lessonId,
  courseSlug,
  onSaved,
  onDeleted,
}: {
  lessonId: number;
  courseSlug: string;
  onSaved: () => void;
  onDeleted: () => void;
}) {
  const [lesson, setLesson] = useState<LessonAuthoring | null>(null);
  const [note, setNote] = useState('');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [checkpoints, setCheckpoints] = useState<{ at_seconds: number; question_id: number }[]>([]);
  const [bank, setBank] = useState<{ id: number; body: string }[]>([]);

  useEffect(() => {
    api<{ data: LessonAuthoring & { checkpoints?: { at_seconds: number; question_id: number }[] | null } }>(`/catalog/lessons/${lessonId}`)
      .then((r) => { setLesson(r.data); setCheckpoints(r.data.checkpoints ?? []); })
      .catch(() => setErr(t('common.error')));
    api<{ data: { id: number; body: string }[] }>(`/assessment/courses/${courseSlug}/questions`)
      .then((r) => setBank(r.data))
      .catch(() => setBank([]));
  }, [lessonId, courseSlug]);

  function patch<K extends keyof LessonAuthoring>(key: K, value: LessonAuthoring[K]) {
    setLesson((prev) => (prev ? { ...prev, [key]: value } : prev));
  }

  async function save() {
    if (!lesson) return;
    setBusy(true); setNote(''); setErr('');
    try {
      await api(`/catalog/lessons/${lesson.id}`, {
        method: 'PATCH',
        body: {
          title: lesson.title,
          type: lesson.type,
          content: lesson.content,
          transcript: lesson.transcript,
          checkpoints: lesson.type === 'video' && checkpoints.length > 0 ? checkpoints : null,
          video_provider: lesson.type === 'video' ? lesson.video_provider : null,
          video_id: lesson.type === 'video' ? lesson.video_id : null,
          is_free_preview: lesson.is_free_preview,
        },
      });
      setNote('تم الحفظ ✓');
      onSaved();
    } catch (e) {
      setErr(e instanceof Error ? e.message : t('common.error'));
    } finally { setBusy(false); }
  }

  async function uploadAsset(file: File) {
    if (!lesson) return;
    setBusy(true); setNote(''); setErr('');
    try {
      const form = new FormData();
      form.append('file', file);
      const res = await fetch(`${API_BASE}/catalog/lessons/${lesson.id}/asset`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'application/json' },
        body: form,
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error((json as { message?: string }).message ?? 'فشل الرفع');
      patch('asset_path', (json as { data: { asset_path: string } }).data.asset_path);
      setNote('رُفع الملف ✓');
      onSaved();
    } catch (e) {
      setErr(e instanceof Error ? e.message : t('common.error'));
    } finally { setBusy(false); }
  }

  async function autoTranscribe() {
    if (!lesson) return;
    setBusy(true); setNote(''); setErr('');
    try {
      const res = await api<{ message: string }>(`/catalog/lessons/${lesson.id}/transcript/auto`, { method: 'POST' });
      setNote(res.message);
    } catch (e) {
      setErr(e instanceof Error ? e.message : t('common.error'));
    } finally { setBusy(false); }
  }

  async function remove() {
    if (!lesson || !window.confirm('حذف هذا الدرس نهائياً؟')) return;
    await api(`/catalog/lessons/${lesson.id}`, { method: 'DELETE' }).catch(() => setErr(t('common.error')));
    onDeleted();
  }

  if (err && !lesson) return <p className="error p-4">{err}</p>;
  if (!lesson) return <p className="label p-4">{t('common.loading')}</p>;

  return (
    <div className="space-y-4 border-t border-brand-100 bg-brand-50/30 p-4">
      <div className="grid gap-3 sm:grid-cols-2">
        <div>
          <label className="label block" htmlFor={`lt-${lesson.id}`}>عنوان الدرس</label>
          <input id={`lt-${lesson.id}`} className="input m-0" value={lesson.title}
            onChange={(e) => patch('title', e.target.value)} />
        </div>
        <div>
          <label className="label block" htmlFor={`lk-${lesson.id}`}>نوع المادة</label>
          <select id={`lk-${lesson.id}`} className="input m-0" value={lesson.type}
            onChange={(e) => patch('type', e.target.value as LessonKind)}>
            {(Object.keys(TYPE_LABELS) as LessonKind[]).map((k) => (
              <option key={k} value={k}>{TYPE_LABELS[k]}</option>
            ))}
          </select>
        </div>
      </div>

      {(lesson.type === 'article' || lesson.type === 'live') && (
        <div>
          <label className="label block" htmlFor={`lc-${lesson.id}`}>
            {lesson.type === 'article' ? 'نص المقال' : 'وصف الجلسة'}
          </label>
          <textarea id={`lc-${lesson.id}`} className="input m-0 min-h-32" value={lesson.content ?? ''}
            onChange={(e) => patch('content', e.target.value)} />
        </div>
      )}

      {(lesson.type === 'image' || lesson.type === 'file') && (
        <div>
          <span className="label block">{lesson.type === 'image' ? 'ملف الصورة' : 'الملف (PDF, Word, …)'}</span>
          {lesson.asset_path && lesson.type === 'image' && (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={lesson.asset_path} alt="" className="mb-2 h-32 rounded-xl object-cover" />
          )}
          {lesson.asset_path && lesson.type === 'file' && (
            <a className="mb-2 block text-sm" href={lesson.asset_path} target="_blank" rel="noreferrer">
              عرض الملف المرفوع ↗
            </a>
          )}
          <label className="btn btn-ghost cursor-pointer">
            {lesson.asset_path ? 'استبدال الملف' : 'رفع ملف'}
            <input type="file" className="hidden"
              accept={lesson.type === 'image' ? 'image/*' : '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip'}
              onChange={(e) => { const f = e.target.files?.[0]; if (f) void uploadAsset(f); }} />
          </label>
        </div>
      )}

      {lesson.type === 'video' && (
        <>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="label block" htmlFor={`lp-${lesson.id}`}>مزوّد الفيديو</label>
              <select id={`lp-${lesson.id}`} className="input m-0" value={lesson.video_provider ?? ''}
                onChange={(e) => patch('video_provider', e.target.value || null)}>
                <option value="">— اختر —</option>
                <option value="youtube">YouTube (غير مُدرج)</option>
                <option value="bunny">Bunny Stream (مُدار)</option>
                <option value="storage">تخزين المنصة</option>
              </select>
            </div>
            <div>
              <label className="label block" htmlFor={`lv-${lesson.id}`}>معرّف الفيديو</label>
              <input id={`lv-${lesson.id}`} className="input m-0" dir="ltr" placeholder="مثل: dQw4w9WgXcQ"
                value={lesson.video_id ?? ''} onChange={(e) => patch('video_id', e.target.value || null)} />
            </div>
          </div>
          <div>
            <div className="mb-1 flex items-center justify-between">
              <label className="label m-0" htmlFor={`tr-${lesson.id}`}>التفريغ النصي للفيديو</label>
              <button type="button" className="btn btn-ghost text-xs" disabled={busy} onClick={() => void autoTranscribe()}>
                ⚡ تفريغ تلقائي (Bunny)
              </button>
            </div>
            <textarea id={`tr-${lesson.id}`} className="input m-0 min-h-24"
              placeholder="يظهر للطالب أسفل الفيديو ويُحسّن البحث وإمكانية الوصول"
              value={lesson.transcript ?? ''} onChange={(e) => patch('transcript', e.target.value)} />
          </div>
        </>
      )}

      {lesson.type === 'video' && (
        <div className="rounded-xl bg-white p-3 ring-1 ring-slate-200">
          <div className="mb-1 flex items-center justify-between">
            <span className="text-sm font-bold text-slate-700">🎯 أسئلة داخل الفيديو (نقاط تحقق)</span>
            <button type="button" className="btn btn-ghost text-xs" disabled={bank.length === 0}
              onClick={() => setCheckpoints((p) => [...p, { at_seconds: 60, question_id: bank[0]?.id ?? 0 }])}>
              + نقطة تحقق
            </button>
          </div>
          {bank.length === 0 && <p className="text-xs text-slate-400">أضف أسئلة لبنك الأسئلة أولاً (تبويب التقييمات).</p>}
          {checkpoints.map((cp, i) => (
            <div key={i} className="mb-1 flex items-center gap-2">
              <input className="input m-0 w-24" type="number" min="0" dir="ltr" title="الثانية"
                value={cp.at_seconds}
                onChange={(e) => setCheckpoints((p) => p.map((x, j) => j === i ? { ...x, at_seconds: parseInt(e.target.value || '0', 10) } : x))} />
              <select className="input m-0 flex-1" value={cp.question_id}
                onChange={(e) => setCheckpoints((p) => p.map((x, j) => j === i ? { ...x, question_id: parseInt(e.target.value, 10) } : x))}>
                {bank.map((q) => <option key={q.id} value={q.id}>{q.body}</option>)}
              </select>
              <button type="button" className="text-red-500" aria-label="حذف"
                onClick={() => setCheckpoints((p) => p.filter((_, j) => j !== i))}>✕</button>
            </div>
          ))}
          {checkpoints.length > 0 && (
            <p className="text-xs text-slate-400">يتوقف الطالب عند الثانية المحددة ليجيب — تغذية راجعة فورية بلا تأثير على الدرجة.</p>
          )}
        </div>
      )}

      <label className="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" checked={lesson.is_free_preview}
          onChange={(e) => patch('is_free_preview', e.target.checked)} />
        درس معاينة مجانية (يظهر للزوار قبل الالتحاق)
      </label>

      <div className="flex flex-wrap items-center gap-2">
        <button className="btn" disabled={busy} onClick={() => void save()}>{t('common.save')}</button>
        <button className="btn btn-ghost text-red-600" disabled={busy} onClick={() => void remove()}>حذف الدرس</button>
        {note && <span className="success text-sm">{note}</span>}
        {err && <span className="error text-sm">{err}</span>}
      </div>
    </div>
  );
}
