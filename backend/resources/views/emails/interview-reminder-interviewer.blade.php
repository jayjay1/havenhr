@component('mail::message')
# Upcoming Interview

Hello {{ $interviewerName }},

You have an interview starting in approximately 1 hour.

**Interview Details:**

- **Candidate:** {{ $candidateName }}
- **Date & Time:** {{ $scheduledAt }}
- **Type:** {{ ucfirst(str_replace('_', ' ', $interviewType)) }}
- **Location:** {{ $location }}

Please ensure you are prepared and available at the scheduled time.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
