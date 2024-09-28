<table>
    <thead>
        <tr>
            <th class="text-left">Field</th>
            <th class="text-left">New</th>
            <th class="text-left">Old</th>
        </tr>
    </thead>
    <tbody>
        @foreach($properties['attributes'] as $key => $newValue)
            @if($newValue !== ($properties['old'][$key] ?? null))
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $labels[$key] ?? $key)) }}</td>
                    <td>{!! $newValue !!}</td>
                    <td>{!! $properties['old'][$key] ?? '' !!}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
