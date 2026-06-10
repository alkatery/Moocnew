<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; }
        .frame {
            margin: 24px;
            border: 6px double #1f3a93;
            padding: 48px 56px;
            text-align: center;
            height: 460px;
            position: relative;
        }
        .title { font-size: 34px; color: #1f3a93; letter-spacing: 1px; }
        .subtitle { font-size: 16px; color: #555; margin-top: 8px; }
        .holder { font-size: 30px; margin: 36px 0 8px; font-weight: bold; }
        .course { font-size: 22px; color: #222; margin-top: 8px; }
        .meta { font-size: 13px; color: #666; margin-top: 40px; }
        .qr { position: absolute; bottom: 24px; left: 40px; width: 120px; }
        .qr img { width: 120px; height: 120px; }
        .serial { position: absolute; bottom: 30px; right: 40px; font-size: 12px; color: #444; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="title">شهادة إتمام</div>
        <div class="subtitle">تشهد المنصة بأن</div>

        <div class="holder">{{ $holderName }}</div>
        <div class="subtitle">قد أتمّ بنجاح {{ $subjectLabel ?? 'دورة' }}</div>
        <div class="course">{{ $courseTitle }}</div>

        <div class="meta">
            تاريخ الإصدار: {{ $issuedAt->format('Y-m-d') }}
        </div>

        <div class="qr">
            <img src="{{ $qrDataUri }}" alt="QR">
            <div style="font-size:10px;color:#777;margin-top:4px;">للتحقق امسح الرمز</div>
        </div>
        <div class="serial">
            الرقم التسلسلي: {{ $serial }}<br>
            {{ $verifyUrl }}
        </div>
    </div>
</body>
</html>
