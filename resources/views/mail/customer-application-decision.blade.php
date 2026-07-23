<x-mail::message>
# Merhaba {{ $application->name }},

@if ($approved)
NeuEmlakCRM müşteri başvurunuz **onaylandı**.

Danışmanlarımız en kısa sürede sizinle iletişime geçecektir.
@else
NeuEmlakCRM müşteri başvurunuz ne yazık ki **reddedildi**.
@endif

@if ($application->admin_note)
**Not:** {{ $application->admin_note }}
@endif

Başvuru bilgileriniz:
- Telefon: {{ $application->phone }}
- E-posta: {{ $application->email }}

Teşekkürler,<br>
{{ config('app.name') }}
</x-mail::message>
