<div style="margin-bottom: 16px;">
    <h2 style="margin: 0;">{{ auth()->user()->company->name ?? config('app.name') }}</h2>
    <p style="margin: 2px 0;">
        {{ __('Timesheet') }} — {{ $data->client->name }}
    </p>
    <p style="margin: 2px 0; color: #555;">
        {{ $data->from->format('Y-m-d') }} → {{ $data->to->format('Y-m-d') }}
        @if ($data->includeValue)
            · {{ $data->currency }}
        @endif
    </p>
</div>
