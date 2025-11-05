<x-mail::message>
# Welcome, {{ $member->name }}!

We're excited to have you join our gym. Your membership is active until **{{ $member->membership_expiry_date }}**.

Please find your personal QR code attached to this email. You will use this to check in at the front desk.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>