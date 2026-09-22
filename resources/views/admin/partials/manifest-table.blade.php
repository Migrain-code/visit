@php $row = 0; @endphp
<table>
    <thead>
        <tr>
            <th class="num">#</th>
            <th>Grup</th>
            <th>Adı Soyadı</th>
            <th>T.C. / Pasaport</th>
            <th>Telefon</th>
            <th class="num">Yaş</th>
            <th class="num">C.</th>
            <th>Biniş</th>
            <th class="sign">Bindi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($groups as $group)
            @forelse ($group->passengers as $passenger)
                <tr class="{{ $loop->first ? 'group-start' : '' }}">
                    <td class="num">{{ ++$row }}</td>
                    @if ($loop->first)
                        <td class="group-cell" rowspan="{{ $group->passengers->count() }}">
                            {{ $group->name ?: $group->contact_name }}
                            <small>{{ $group->code }} · {{ $group->passenger_count }} kişi</small>
                            <small>{{ $group->contact_phone }}</small>
                            @if ($group->notes)<small>Not: {{ \Illuminate\Support\Str::limit($group->notes, 80) }}</small>@endif
                        </td>
                    @endif
                    <td>{{ $passenger->full_name }}</td>
                    <td>{{ $passenger->identity ?: '—' }}</td>
                    <td>{{ $passenger->phone ?: '' }}</td>
                    <td class="num">{{ $passenger->age }}</td>
                    <td class="num">{{ $passenger->gender?->short() }}</td>
                    @if ($loop->first)
                        <td rowspan="{{ $group->passengers->count() }}">{{ $group->pickup_point }}</td>
                    @endif
                    <td class="sign"></td>
                </tr>
            @empty
                <tr class="group-start">
                    <td class="num">—</td>
                    <td class="group-cell">{{ $group->name ?: $group->contact_name }}<small>{{ $group->code }}</small></td>
                    <td colspan="7" class="muted">Bu grubun yolcu bilgileri girilmemiş.</td>
                </tr>
            @endforelse
        @empty
            <tr><td colspan="9" class="muted" style="text-align:center; padding: 14px;">Bu araçta yolcu yok.</td></tr>
        @endforelse
    </tbody>
</table>
