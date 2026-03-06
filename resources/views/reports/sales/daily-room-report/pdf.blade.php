@extends('reports.layouts.pdf.main')

@section('content')
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            /*border: 1px solid #d3d3d3; !* light gray border *!*/
            padding: 4px;
            font-size: 12px;
            vertical-align: middle;
        }

        th {
            background-color: #f0f0f0; /* light gray header */
            font-weight: bold;
            text-align: center;
        }

        tbody tr:nth-child(even) {
            background-color: #fafafa; /* light zebra */
        }

        .extra-bed-row {
            background-color: #fff3cd; /* yellow for extra bed rows */
            font-style: italic;
        }
    </style>

    <div class="fo-12 top-5">
        <div class="mt-3">
            <table>
                <thead>
                {{-- Header --}}
                <tr>
                    <th rowspan="2">SL#</th>
                    <th rowspan="2">ROOM#</th>
                    <th rowspan="2">CATEGORY</th>
                    <th rowspan="2">RESV & STATUS</th>
                    <th colspan="3">GUEST INFO</th>
                    <th rowspan="2">REFERENCE</th>
                    <th rowspan="2">CREATED BY</th>
                    <th rowspan="2">PERIOD</th>
                    <th colspan="3">PRICE</th>
                    <th rowspan="2">TIP NOTE</th>
                </tr>
                <tr>
                    <th>NAME</th>
                    <th>CONTACT</th>
                    <th>ADDRESS</th>
                    <th>ROOM RENT</th>
                    <th>COMMISSION</th>
                    <th>NET RENT</th>
                </tr>
                </thead>
                <tbody>
                {{-- Data rows --}}
                @foreach ($data['rooms'] as $room)
                    @php
                        $allocation = $room->room_sale_allocation[0] ?? null;
                        $extraBed = $room->room_sale_allocation[1] ?? null;
                        $saleRoom = $allocation?->saleRoom;
                        $saleTip = $saleRoom->sale_tip[0] ?? null;

                        $rowClass = '';
                        if($saleRoom?->status === 'checkedin') $rowClass = 'status-checkedin';
                        elseif($saleRoom?->status === 'checkedout') $rowClass = 'status-checkedout';
                    @endphp

                    {{-- Main room row --}}
                    <tr class="{{ $rowClass }} border">
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">{{ $loop->iteration }}</td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">{{ $room->name }}</td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">{{ $room->roomName?->category?->name ?? '-' }}</td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">
                            {{ $saleRoom?->code ?? '-' }}<br>{{ $saleRoom?->status ?? '-' }}
                        </td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">
                            {{ $saleRoom?->guest_name_title ?? '-' }} {{ $saleRoom?->guest_name_first ?? '' }} {{ $saleRoom?->guest_name_last ?? '' }}
                        </td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">{{ $saleRoom?->guest_mobile ?? '-' }}</td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">{{ $saleRoom?->guest_address ?? '-' }}</td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">
                            @if($saleRoom?->sale_agent)
                                [{{ $saleRoom->sale_agent->user_name }}]<br>{{ $saleRoom->sale_agent->name }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $saleRoom?->sale_user?->name ?? '-' }}</td>
                        <td>
                            @if($saleRoom)
                                {{ \Carbon\Carbon::parse($saleRoom->date_checkin)->format('d/m/Y') }}<br>
                                to<br>
                                {{ \Carbon\Carbon::parse($saleRoom->date_checkout)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $allocation?->rate_sale ?? '-' }}</td>
                        <td>{{ $allocation?->tip_amount ?? '-' }}</td>
                        <td>{{ $allocation?->rate_net ?? '-' }}</td>
                        <td rowspan="{{ $extraBed ? '2' : '1' }}">{{ $saleTip->note ?? '-' }}</td>
                    </tr>

                    {{-- Extra Bed row --}}
                    @if($extraBed)
                        <tr class="extra-bed-row">
                            <td colspan="2" style="text-align:right; font-size: 16px; font-weight: bold">Extra Bed:</td>
                            <td>{{ $extraBed->rate_sale ?? '-' }}</td>
                            <td>{{ $extraBed->tip_amount ?? '-' }}</td>
                            <td>{{ $extraBed->rate_net ?? '-' }}</td>
                        </tr>
                    @endif
                @endforeach
                <tr>
                    <td colspan="16"></td>
                </tr>

                <tr>
                    <td colspan="10"></td>
                    <th colspan="3" style="text-align:left;">Total Sold Room</th>
                    <td colspan="2" style="text-align:right;">
                        {{ $data['summary']['total_sold_room'] }}
                    </td>
                </tr>

                <tr>
                    <td colspan="10"></td>
                    <th colspan="3" style="text-align:left;">Total Not Sold Room</th>
                    <td colspan="2" style="text-align:right;">
                        {{ $data['summary']['total_not_sold_room'] }}
                    </td>
                </tr>

                <tr>
                    <td colspan="10"></td>
                    <th colspan="3" style="text-align:left;">Total Sale</th>
                    <td colspan="2" style="text-align:right;">
                        {{ number_format($data['summary']['total_sale'], 2) }}
                    </td>
                </tr>

                <tr>
                    <td colspan="10"></td>
                    <th colspan="3" style="text-align:left;">Total Commission</th>
                    <td colspan="2" style="text-align:right;">
                        {{ number_format($data['summary']['total_commision'], 2) }}
                    </td>
                </tr>

                <tr>
                    <td colspan="10"></td>
                    <th colspan="3" style="text-align:left;">Total Net Sale</th>
                    <td colspan="2" style="text-align:right;">
                        {{ number_format($data['summary']['total_net_sale'], 2) }}
                    </td>
                </tr>

                <tr>
                    <td colspan="10"></td>
                    <th colspan="3" style="text-align:left;">Total Income</th>
                    <td colspan="2" style="text-align:right;">
                        {{ number_format($data['summary']['total_income'], 2) }}
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
