{{-- Quotation Rate Card Matrix Table
     Quotations ONLY use station-based rates with capacities.
     Rows: Stations (Mumbai, Delhi, etc.)
     Columns: Capacities (9MT, 10MT, 12MT, etc.)
     Values: Rates for each station/capacity combination --}}
<table width="100%" class="items" cellspacing="0" border="0">
    <thead>
        <tr class="item-table-heading-row">
            <th width="2%" class="pr-20 text-right item-table-heading">#</th>
            <th width="25%" class="pl-0 text-left item-table-heading">Station</th>
            @foreach($units as $unit)
                <th class="pr-20 text-right item-table-heading">{{ $unit->name }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @php
            $index = 1
        @endphp
        @foreach ($estimate->quotationStations as $station)
            <tr class="item-row">
                <td
                    class="pr-20 text-right item-cell"
                    style="vertical-align: top;"
                >
                    {{$index}}
                </td>
                <td
                    class="pl-0 text-left item-cell"
                >
                    <span>{{ $station->name }}</span>
                </td>
                @foreach($units as $unit)
                    <td
                        class="pr-20 text-right item-cell"
                        style="vertical-align: top;"
                    >
                        @php
                            $rate = $station->rates()
                                ->where('capacity', $unit->name)
                                ->first();
                        @endphp
                        @if ($rate)
                            {!! format_money_pdf($rate->rate, $estimate->customer->currency) !!}
                        @else
                            —
                        @endif
                    </td>
                @endforeach
            </tr>
            @php
                $index += 1
            @endphp
        @endforeach
    </tbody>
</table>
