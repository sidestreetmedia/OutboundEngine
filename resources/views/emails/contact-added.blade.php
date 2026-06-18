<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Added to HubSpot</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f7; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#181b22;">
    <div style="max-width:560px; margin:0 auto; padding:28px 20px;">
        <div style="font-size:13px; color:#687083; margin-bottom:14px;">
            Outbound<span style="color:#3538cd;">Engine</span> · new CRM contact
        </div>

        <div style="background:#ffffff; border:1px solid #e4e7ec; border-radius:10px; padding:24px 26px;">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#0c7a54; font-weight:600; margin-bottom:6px;">
                Added to HubSpot
            </div>
            <h1 style="font-size:21px; margin:0 0 4px; letter-spacing:-0.01em;">{{ $name }}</h1>
            <p style="margin:0 0 20px; color:#687083; font-size:14px;">
                Replied positively to the current CTA, so they were just added to your CRM.
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; font-size:14px; border-collapse:collapse;">
                <tbody>
                    @php
                        $rows = [
                            'Contact' => trim($name . ($lead->title ? ' · ' . $lead->title : '')),
                            'Company' => $lead->company ?: '—',
                            'Email' => $lead->email,
                            'Campaign' => $summary['campaign'],
                            'CTA / offer' => $summary['offer'],
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <tr>
                            <td style="padding:8px 0; color:#687083; width:120px; vertical-align:top; border-bottom:1px solid #f0f1f4;">{{ $label }}</td>
                            <td style="padding:8px 0; vertical-align:top; border-bottom:1px solid #f0f1f4;">{{ $value }}</td>
                        </tr>
                    @endforeach
                    @if (! empty($summary['reply_snippet']))
                        <tr>
                            <td style="padding:8px 0; color:#687083; vertical-align:top; border-bottom:1px solid #f0f1f4;">Their reply</td>
                            <td style="padding:8px 0; vertical-align:top; border-bottom:1px solid #f0f1f4; color:#181b22;">
                                <span style="display:block; border-left:3px solid #c9e6d8; padding:2px 0 2px 10px;">{{ $summary['reply_snippet'] }}</span>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:8px 0; color:#687083; vertical-align:top;">HubSpot contact</td>
                        <td style="padding:8px 0; vertical-align:top;">
                            @if ($link)
                                <a href="{{ $link }}" style="color:#3538cd; text-decoration:none;">Open record ({{ $contactId }})</a>
                            @else
                                {{ $contactId ?: '—' }}
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p style="font-size:12px; color:#9aa1b1; margin:16px 2px 0;">
            Sent by OutboundEngine when a contact is added to HubSpot.
        </p>
    </div>
</body>
</html>
