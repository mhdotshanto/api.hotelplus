@extends('reports.layouts.html.main')
@section('content')
<div class="fo-12 top-5">
    <p class="text-center p-0 m-0 fo-12" style="font-size:22px">CHECKOUT REPORT</p>
    <p class="text-center p-0 m-0 fo-12" style="font-size:18px"><strong>DATE:</strong> {{ $from_date }} TO {{ $to_date }}</p>
    <p class="text-center p-0 m-0 fo-12" style="font-size:16px"><strong>STAFF: </strong>{{ !is_null($sale_agents) ? $sale_agents : "All" }}</p>
    <div class="mt-3">
        <table class="table border">
            <tr style="font-size:11px;">
                <th style="text-align:center;">SL#</th>
                <th style="text-align:center;">RES#</th>
                <th style="text-align:center;">DATE</th>
                <th style="text-align:center;">RES PERIOD</th>
                <th style="text-align:center;">GUEST</th>
                <th style="text-align:center;">REFERENCE</th>
                <th style="text-align:center;">ROOM</th>
                <th style="text-align:center;">GROSS PRICE</th>
                <th style="text-align:center;">NET PRICE</th>
                <th style="text-align:center;">DUE</th>
                <th style="text-align:center;">TIP</th>
            </tr>
            @foreach ($saleRooms as $sale)
                <tr>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $loop->iteration }}</td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $sale->code }}</td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">
                        {{ \Carbon\Carbon::parse($sale->date)->format('d/m/Y') }}
                    </td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">
                        {{ \Carbon\Carbon::parse($sale->date_checkin)->format('d/m/Y') }}
                        <br>
                        to
                        <br>
                        {{ \Carbon\Carbon::parse($sale->date_checkout)->format('d/m/Y') }}
                    </td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">
                        {{ $sale?->guest_name_title ?? '-' }} {{ $sale?->guest_name_first ?? '' }} {{ $sale?->guest_name_last ?? '' }}
                        <br>
                        {{ $sale->guest_mobile }}
                    </td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $sale->sale_agent?->name ?? '-' }}</td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $sale->room_qty }}</td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $sale->price_gross }}</td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $sale->price_net }}</td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $sale->total_due }}</td>
                    <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $sale->tip_amount }}</td>
                </tr>
            @endforeach
            <tr style="font-weight:bold; font-size:12px; text-align:center;">
                <td colspan="6" style="text-align: end">TOTAL</td>
                <td style="text-align: end">{{ $totals['room_qty'] }}</td>
                <td style="text-align: end">{{ number_format($totals['gross_price'], 2) }}</td>
                <td style="text-align: end">{{ number_format($totals['net_price'], 2) }}</td>
                <td style="text-align: end">{{ number_format($totals['due'], 2) }}</td>
                <td style="text-align: end">{{ number_format($totals['tip'], 2) }}</td>
            </tr>
        </table>
    </div>
</div>
@endsection
