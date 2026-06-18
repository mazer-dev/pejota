<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Timesheet') }} - {{ $data->client->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body, h1, h2, h3, p, span, div, th, td { font-family: DejaVu Sans; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background: #f4f4f4; }
        .group-label { background: #eaeaea; font-weight: bold; }
        .subtotal td, .grand-total td { font-weight: bold; }
        .num { text-align: right; }
    </style>
</head>
<body>
    @if ($layout->headerView())
        @include($layout->headerView(), ['data' => $data])
    @endif

    @php($columns = $layout->columns($request))
    @php($money = fn (float $value) => \Illuminate\Support\Number::currency($value, $data->currency, \App\Helpers\PejotaHelper::getUserLocate()))

    @forelse ($data->groups as $group)
        <table>
            <thead>
                <tr class="group-label"><td colspan="{{ count($columns) }}">{{ $group->label }}</td></tr>
                <tr>
                    @foreach ($columns as $col)
                        <th class="{{ in_array($col['type'], ['duration', 'money']) ? 'num' : '' }}">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($group->entries as $entry)
                    <tr>
                        @foreach ($columns as $col)
                            <td class="{{ in_array($col['type'], ['duration', 'money']) ? 'num' : '' }}">
                                @switch($col['key'])
                                    @case('date') {{ $entry->date->format('Y-m-d') }} @break
                                    @case('description') {{ $entry->description }} @break
                                    @case('taskTitle') {{ $entry->taskTitle }} @break
                                    @case('projectName') {{ $entry->projectName }} @break
                                    @case('minutes') {{ \App\Helpers\PejotaHelper::formatDuration($entry->minutes) }} @break
                                    @case('rate') {{ $entry->rate === null ? '' : $money($entry->rate) }} @break
                                    @case('value') {{ $money($entry->value) }} @break
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="subtotal">
                    @foreach ($columns as $i => $col)
                        <td class="{{ in_array($col['type'], ['duration', 'money']) ? 'num' : '' }}">
                            @if ($i === 0) {{ __('Subtotal') }}
                            @elseif ($col['key'] === 'minutes') {{ \App\Helpers\PejotaHelper::formatDuration($group->subtotalMinutes) }}
                            @elseif ($col['key'] === 'value') {{ $money($group->subtotalValue) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @empty
        <p>{{ __('No entries for this period.') }}</p>
    @endforelse

    @if ($data->groups->isNotEmpty())
        <table>
            <tr class="grand-total">
                @foreach ($columns as $i => $col)
                    <td class="{{ in_array($col['type'], ['duration', 'money']) ? 'num' : '' }}">
                        @if ($i === 0) {{ __('Total') }}
                        @elseif ($col['key'] === 'minutes') {{ \App\Helpers\PejotaHelper::formatDuration($data->grandTotalMinutes) }}
                        @elseif ($col['key'] === 'value') {{ $money($data->grandTotalValue) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
</body>
</html>
