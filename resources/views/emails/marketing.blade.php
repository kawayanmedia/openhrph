<x-mail::message>
# Welcome to {{ config('app.name') }}

Hi {{ $user->email }},

{{ $content }}
 
Thanks,<br>
{{ config('app.name') }}
</x-mail::message>