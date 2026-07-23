<x-mail::message>
# Merhaba {{ $user->name }},

**Dikkat:** "{{ $propertyTitle }}" sözleşmenizin bitiş tarihi yaklaşıyor.

- Bitiş tarihi: **{{ $endDate }}**
- Kalan süre: **{{ $daysLeft }} gün**

<x-mail::button :url="$portalUrl">
Portala Git
</x-mail::button>

Bu hatırlatmayı Ayarlar sayfasından kapatabilir veya süresini değiştirebilirsiniz.

Teşekkürler,<br>
{{ config('app.name') }}
</x-mail::message>
