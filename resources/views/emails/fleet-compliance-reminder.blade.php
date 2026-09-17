<x-mail::message>
# Fleet compliance reminder

The following fleet documents require attention for {{ $company->name }}.

@foreach ($items as $item)
* **{{ $item['subject'] }}** — {{ $item['document_type'] }} expires on {{ $item['expires_on'] }} ({{ $item['days_remaining'] === 0 ? 'today' : $item['days_remaining'].' days remaining' }})
@endforeach

Please update the document and confirmed expiry date in Fleet Management.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
