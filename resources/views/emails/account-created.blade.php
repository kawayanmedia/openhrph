<x-mail::message>
# Welcome to {{ config('app.name') }}

Your email: {{ $user->email }}

Your temporary password: {{ $password }}
 
<x-mail::button :url="$url">
Login Now
</x-mail::button>
 
Thanks,<br>
{{ config('app.name') }}
</x-mail::message>