<x-mail::message>
# {{ $isApproved ? 'Vasa ziadost bola schvalena' : 'Vasa ziadost bola zamietnuta' }}

Dobry den,

Vasa ziadost z formulara **{{ $form->name }}** bola {{ $isApproved ? 'schvalena' : 'zamietnuta' }}.

@if($adminResponse)
## Odpoved administratora

{{ $adminResponse }}
@endif

## Detaily ziadosti

- **Formular:** {{ $form->name }}
- **Datum odoslania:** {{ $submission->created_at->format('d.m.Y H:i') }}
- **Datum rozhodnutia:** {{ $reviewedAt?->format('d.m.Y H:i') ?? '-' }}
- **Stav:** {{ $isApproved ? 'Schvalena' : 'Zamietnuta' }}

<x-mail::button :url="$url">
Zobrazit detail ziadosti
</x-mail::button>

S pozdravom,<br>
{{ config('app.name') }}
</x-mail::message>
