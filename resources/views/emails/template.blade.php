<!DOCTYPE html>
<html lang="{{ $locale === 'ar' ? 'ar' : 'en' }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(15, 23, 42, 0.08);">
        <div style="background:#2952cc; padding:20px 24px; color:#ffffff; font-size:22px; font-weight:700;">
            {{ config('app.name', 'TRAC') }}
        </div>
        <div style="padding:24px; line-height:1.7; font-size:15px;">
            {!! $content !!}
        </div>
    </div>
</body>
</html>
