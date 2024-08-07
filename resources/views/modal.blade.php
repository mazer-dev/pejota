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
            @php
                $oldValue = $properties['old'][$key] ?? null;
            @endphp
            @if($newValue !== $oldValue)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                    <td>{!! $newValue !!}</td>
                    <td>{!! $oldValue !!}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
