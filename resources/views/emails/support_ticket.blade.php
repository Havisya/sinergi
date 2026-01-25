@component('mail::message')
# Tiket Dukungan Baru Telah Dibuat

Detail tiket dari user:

| Field | Value |
| :--- | :--- |
| **Nama Pengirim** | {{ $name }} |
| **Email Pengirim** | {{ $email }} |
| **Subjek** | {{ $subject }} |

---

**Pesan:**
{{ $message }}

@component('mail::button', ['url' => url('/')])
Akses Aplikasi
@endcomponent

Terima kasih,<br>
Tim Support {{ config('app.name') }}
@endcomponent
