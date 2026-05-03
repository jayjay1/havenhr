@component('mail::message')
# Welcome to HavenHR

Hello {{ $userName }},

An account has been created for you on HavenHR. Here are your login credentials:

- **Email:** {{ $email }}
- **Temporary Password:** {{ $temporaryPassword }}

Please log in and change your password as soon as possible.

@component('mail::button', ['url' => $loginUrl])
Log In to HavenHR
@endcomponent

Thanks,<br>
HavenHR
@endcomponent
