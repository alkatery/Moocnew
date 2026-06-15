/**
 * G5 (B3-wcag-audit §3.1): مكوّنات رسائل الحالة الموحّدة
 * ErrorMsg  — role="alert" aria-live="assertive"  (P1: تُعلَن فوراً)
 * SuccessMsg — role="status" aria-live="polite"   (polite: لا تقاطع)
 * كلاهما يعيد null عند الرسالة الفارغة — آمن للاستبدال المباشر.
 */

export function ErrorMsg({ msg }: { msg: string }) {
  if (!msg) return null;
  return (
    <p className="error mb-3" role="alert" aria-live="assertive">
      {msg}
    </p>
  );
}

export function SuccessMsg({ msg }: { msg: string }) {
  if (!msg) return null;
  return (
    <p className="success mb-3" role="status" aria-live="polite">
      {msg}
    </p>
  );
}
