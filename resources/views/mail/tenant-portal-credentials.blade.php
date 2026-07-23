<x-mail::message>
# Merhaba {{ $user->name }},

Kiracı portalı başvurunuz **onaylandı**. Artık NeuEmlakCRM kiracı hesabınıza giriş yapabilirsiniz.

**Giriş bilgileri**
- E-posta: {{ $user->email }}
@if ($plainPassword)
- Geçici şifre: `{{ $plainPassword }}`
@else
- Şifre: Daha önce oluşturduğunuz şifreyi kullanın.
@endif

@if ($application->admin_note)
**Not:** {{ $application->admin_note }}
@endif

<x-mail::button :url="$loginUrl">
Giriş Yap
</x-mail::button>

İlk girişten sonra profil ayarlarından şifrenizi değiştirmenizi öneririz.

Teşekkürler,<br>
{{ config('app.name') }}
</x-mail::message>
