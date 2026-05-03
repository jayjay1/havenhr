@component('mail::message')
# Interview Reminder

Hello {{ $candidateName }},

This is a reminder that you have an upcoming interview for **{{ $jobTitle }}**.

**Interview Details:**

- **Date & Time:** {{ $scheduledAt }}
- **Type:** {{ ucfirst(str_replace('_', ' ', $interviewType)) }}
- **Location:** {{ $location }}

Please make sure to prepare in advance and be available at the scheduled time.

Good luck!

Thanks,<br>
{{ config('app.name') }}
@endcomponent
