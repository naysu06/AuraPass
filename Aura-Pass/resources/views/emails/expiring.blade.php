<x-mail::message>
# Hello {{ $member->name }},

This is a friendly reminder that your gym membership is set to expire on **{{ $member->membership_expiry_date->format('M d, Y') }}**.

To avoid interruption to your workout routine, please visit the front desk to renew your subscription.

<x-mail::button :url="config('app.url')">
View Gym Info
</x-mail::button>

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>