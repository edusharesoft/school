<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $subjectLine }}</title></head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:'Hind Siliguri','Segoe UI',Arial,sans-serif;color:#0f172a">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden">
        <tr><td style="background:#0f766e;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:600">{{ $branchName ?? config('app.name') }}</td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.7">
            <div style="font-size:17px;font-weight:600;margin-bottom:12px">{{ $subjectLine }}</div>
            {!! nl2br(e($messageText)) !!}
            @if($link)
                <p style="margin:20px 0 0"><a href="{{ $link }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px">{{ __('Open') }}</a></p>
            @endif
        </td></tr>
        <tr><td style="padding:12px 24px;background:#f8fafc;color:#64748b;font-size:12px">{{ __('This message was sent by :school. Contact the school office to stop receiving e-mails.', ['school' => $branchName ?? config('app.name')]) }}</td></tr>
    </table>
</body>
</html>
