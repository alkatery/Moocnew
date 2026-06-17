// Arabic labels and badge tones for backend status strings, shared by the
// internal pages so the same status always reads and looks the same.
const STATUS_AR: Record<string, string> = {
  draft: 'مسودة',
  pending_review: 'قيد المراجعة',
  published: 'منشورة',
  rejected: 'مرفوضة',
  archived: 'مؤرشفة',
  pending: 'قيد الانتظار',
  active: 'نشط',
  completed: 'مكتمل',
  expired: 'منتهٍ',
  refunded: 'مستردّ',
  paid: 'مدفوع',
  failed: 'فاشل',
  cancelled: 'ملغى',
  new: 'جديد',
  handled: 'تمت معالجته',
  live_session: 'جلسة مباشرة',
};

export function statusLabel(status: string): string {
  return STATUS_AR[status] ?? status;
}

export function badgeTone(status: string): string {
  if (['published', 'active', 'completed', 'paid', 'handled'].includes(status)) {
    return 'bg-emerald-50 text-emerald-700';
  }
  if (['pending', 'pending_review', 'draft', 'new'].includes(status)) {
    return 'bg-amber-50 text-amber-700';
  }
  if (['rejected', 'failed', 'refunded', 'expired', 'cancelled'].includes(status)) {
    return 'bg-red-50 text-red-700';
  }
  return '';
}

const NOTIFICATION_TYPES_AR: Record<string, string> = {
  enrollment_activated: 'تفعيل الالتحاق',
  enrollment_completed: 'إكمال الدورة',
  certificate_issued: 'إصدار شهادة',
  session_reminder: 'تذكير بجلسة مباشرة',
};

const CHANNELS_AR: Record<string, string> = {
  database: 'داخل المنصة',
  mail: 'البريد',
  sms: 'SMS',
  whatsapp: 'واتساب',
  push: 'إشعار فوري',
};

export function notificationTypeLabel(type: string): string {
  return NOTIFICATION_TYPES_AR[type] ?? type;
}

export function channelLabel(channel: string): string {
  return CHANNELS_AR[channel] ?? channel;
}
