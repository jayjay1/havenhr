@component('mail::message')
# Application Update

Hello {{ $candidateName }},

Your application for **{{ $jobTitle }}** at **{{ $companyName }}** has moved to a new stage: **{{ $stageName }}**.

We'll keep you updated as your application progresses.

@component('mail::button', ['url' => $preferencesUrl])
Manage Notification Preferences
@endcomponent

Thanks,<br>
{{ $companyName }}

<small>[Manage your notification preferences]({{ $preferencesUrl }})</small>
@endcomponent
