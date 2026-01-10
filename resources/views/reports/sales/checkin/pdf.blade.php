@extends('reports.layouts.pdf.main')
@section('content')

    <style>
        /* Table styling */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .report-table th, .report-table td {
            /*border: 1px solid #000;*/
            padding: 5px;
        }

        .report-table th {
            background-color: #f2f2f2;
            /*color: white;*/
            text-align: center;
        }

        .report-table td {
            text-align: center;
            vertical-align: middle;
        }

        /* Alternating row colors */
        .report-table tr.even {
            background-color: #f2f2f2;
        }

        .report-table tr.odd {
            background-color: #ffffff;
        }

        /* Right align for numeric columns */
        .text-right {
            text-align: right;
        }

        /* Totals row */
        .totals-row {
            background-color: #d9edf7;
            font-weight: bold;
        }

        /* Header */
        .report-title {
            font-size: 22px;
            text-align: center;
            margin: 0;
            padding: 0;
        }

        .report-subtitle {
            font-size: 18px;
            text-align: center;
            margin: 0;
            padding: 0;
        }
    </style>

    <div class="fo-12 top-5">
        <p class="report-title">CHECKOUT REPORT</p>
        <p class="report-subtitle"><strong>DATE:</strong> {{ $data['from_date'] }} TO {{ $data['to_date'] }}</p>
        <p class="text-center p-0 m-0 fo-12" style="font-size:16px"><strong>STAFF: </strong>{{ !is_null($data['sale_agents']) ? $data['sale_agents'] : "All" }}</p>

        <div class="mt-3">
            <table class="report-table">
                <thead>
                <tr>
                    <th>SL#</th>
                    <th>RES#</th>
                    <th>DATE</th>
                    <th>RES PERIOD</th>
                    <th>GUEST</th>
                    <th>REFERENCE</th>
                    <th>ROOM</th>
                    <th>GROSS PRICE</th>
                    <th>NET PRICE</th>
                    <th>DUE</th>
                    <th>TIP</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($data['saleRooms'] as $sale)
                    <tr class="{{ $loop->even ? 'even' : 'odd' }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $sale->code }}</td>
                        <td>{{ \Carbon\Carbon::parse($sale->date)->format('d/m/Y') }}</td>
                        <td>
                            {{ \Carbon\Carbon::parse($sale->date_checkin)->format('d/m/Y') }}<br>
                            to<br>
                            {{ \Carbon\Carbon::parse($sale->date_checkout)->format('d/m/Y') }}
                        </td>
                        <td>
                            {{ $sale?->guest_name_title ?? '-' }} {{ $sale?->guest_name_first ?? '' }} {{ $sale?->guest_name_last ?? '' }}<br>
                            {{ $sale->guest_mobile }}
                        </td>
                        <td>{{ $sale->sale_agent?->name ?? '-' }}</td>
                        <td>{{ $sale->room_qty }}</td>
                        <td class="text-right">{{ number_format($sale->price_gross,2) }}</td>
                        <td class="text-right">{{ number_format($sale->price_net,2) }}</td>
                        <td class="text-right">{{ number_format($sale->total_due,2) }}</td>
                        <td class="text-right">{{ number_format($sale->tip_amount,2) }}</td>
                    </tr>
                @endforeach

                {{-- Totals row --}}
                <tr class="totals-row">
                    <td colspan="6" class="text-right">TOTAL</td>
                    <td>{{ $data['totals']['room_qty'] }}</td>
                    <td class="text-right">{{ number_format($data['totals']['gross_price'],2) }}</td>
                    <td class="text-right">{{ number_format($data['totals']['net_price'],2) }}</td>
                    <td class="text-right">{{ number_format($data['totals']['due'],2) }}</td>
                    <td class="text-right">{{ number_format($data['totals']['tip'],2) }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
