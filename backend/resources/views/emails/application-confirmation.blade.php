@component('mail::message')
# Application Received

Hello {{ $candidateName }},

Your application for **{{ $jobTitle }}** at **{{ $companyName }}** has been received.

Our team will review your application and get back to you. You can track your application status in your candidate dashboard.

@component('mail::button', ['url' => $preferencesUrl])
Manage Notification Preferences
@endcomponent

Thanks,<br>
{{ $companyName }}

<small>[Manage your notification preferences]({{ $preferencesUrl }})</small>
@endcomponent
