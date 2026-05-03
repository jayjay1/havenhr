@component('mail::message')
# Reset Your Password

Hello {{ $userName }},

We received a request to reset your password. Click the button below to set a new password. This link expires in 60 minutes.

@component('mail::button', ['url' => $resetUrl])
Reset Password
@endcomponent

If you did not request a password reset, no action is needed.

Thanks,<br>
HavenHR
@endcomponent
