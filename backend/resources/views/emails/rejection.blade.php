@component('mail::message')
# Application Update

Hello {{ $candidateName }},

Thank you for your interest in the **{{ $jobTitle }}** position at **{{ $companyName }}**.

After careful consideration, we've decided to move forward with other candidates for this role. This was a difficult decision, and we appreciate the time and effort you put into your application.

We encourage you to apply for future openings that match your skills and experience. We wish you the best in your job search.

@component('mail::button', ['url' => $preferencesUrl])
Manage Notification Preferences
@endcomponent

Thanks,<br>
{{ $companyName }}

<small>[Manage your notification preferences]({{ $preferencesUrl }})</small>
@endcomponent
