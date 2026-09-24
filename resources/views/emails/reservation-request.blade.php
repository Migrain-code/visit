<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"><title>Yeni iletişim talebi</title></head>
<body style="font-family: -apple-system, 'Segoe UI', Arial, sans-serif; color: #0d2544; line-height: 1.55; margin: 0; padding: 24px; background: #f2f6fb;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #dfe9f3;">
        <div style="background: #0d2544; color: #fff; padding: 20px 24px;">
            <h1 style="margin: 0; font-size: 18px;">Yeni iletişim talebi</h1>
            <p style="margin: 4px 0 0; font-size: 13px; opacity: .8;">{{ $reservation->created_at?->format('d.m.Y H:i') }}</p>
        </div>
        <div style="padding: 24px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                @foreach ([
                    'Ad Soyad' => $reservation->name,
                    'Telefon' => $reservation->phone,
                    'E-posta' => $reservation->email,
                    'Tur' => $reservation->tour_label,
                    'Kişi sayısı' => $reservation->people_count,
                ] as $label => $value)
                    @continue(blank($value))
                    <tr>
                        <td style="padding: 8px 0; color: #7a8a9a; width: 38%; border-bottom: 1px solid #e8f0f8;">{{ $label }}</td>
                        <td style="padding: 8px 0; font-weight: 600; border-bottom: 1px solid #e8f0f8;">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>

            @if ($reservation->message)
                <p style="margin: 18px 0 6px; color: #7a8a9a; font-size: 13px;">Mesajı</p>
                <p style="margin: 0; white-space: pre-line;">{{ $reservation->message }}</p>
            @endif

            <p style="margin: 24px 0 0;">
                <a href="{{ $adminUrl }}" style="display: inline-block; background: #ffc530; color: #0d2544; text-decoration: none; padding: 11px 22px; border-radius: 999px; font-weight: 700;">Panelde aç</a>
                <a href="https://wa.me/{{ ltrim(phone_digits($reservation->phone), '+') }}" style="display: inline-block; margin-left: 8px; color: #0d7de0; font-weight: 600;">WhatsApp'tan yaz</a>
            </p>
        </div>
    </div>
</body>
</html>
