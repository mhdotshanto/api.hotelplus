@extends('reports.layouts.excel.main')
@section('content')
    <div class="fo-12 top-5">
        <div class="mt-3">
            <table>
                <tbody>
                    {{-- Header --}}
                    <tr style="font-size:11px;">
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">SL#</th>
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">ROOM#</th>
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">CATEGORY</th>
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">RESV AND STATUS</th>
                        <th style="text-align:center; vertical-align:middle;" colspan="3">GUEST INFO</th>
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">REFERENCE</th>
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">CREATED BY</th>
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">PERIOD</th>
                        <th style="text-align:center; vertical-align:middle;" colspan="3">PRICE</th>
                        <th style="text-align:center; vertical-align:middle;" rowspan="2">TIP NOTE</th>
                    </tr>
                    <tr style="font-size:11px;">
                        <th style="text-align:center;">NAME</th>
                        <th style="text-align:center;">CONTACT</th>
                        <th style="text-align:center;">ADDRESS</th>
                        <th style="text-align:center;">ROOM RENT</th>
                        <th style="text-align:center;">COMMISSION</th>
                        <th style="text-align:center;">NET RENT</th>
                    </tr>

                    {{-- Rows --}}
                    @foreach ($data['rooms'] as $room)
                        @php
                            $allocation = $room->room_sale_allocation[0] ?? null;
                            $saleRoom = $allocation?->saleRoom;
                            $xb = $room->room_sale_allocation[1] ?? null;
                            $saleTip = $saleRoom->sale_tip[0] ?? null;
                        @endphp
                        <tr>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">{{ $loop->iteration }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">{{ $room->name }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">{{ $room->roomName?->category?->name ?? '-' }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">
                                {{ $saleRoom?->code ?? '-' }}
                                <br>
                                {{ $saleRoom?->status ?? '-' }}
                            </td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">{{ $saleRoom?->guest_name_title ?? '-' }} {{ $saleRoom?->guest_name_first ?? '' }} {{ $saleRoom?->guest_name_last ?? '' }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">{{ $saleRoom?->guest_mobile ?? '-' }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">{{ $saleRoom?->guest_address ?? '-' }}</td>
                            @if($saleRoom)
                                <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">
                                    [{{ $saleRoom->sale_agent->user_name }}]
                                    <br>
                                    {{ $saleRoom->sale_agent->name }}
                                </td>
                            @else
                                <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">-</td>
                            @endif
                            <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $saleRoom?->sale_user?->name ?? '-' }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;">
                                @if($saleRoom)
                                    {{ \Carbon\Carbon::parse($saleRoom->date_checkin)->format('d/m/Y') }}
                                    <br>
                                    to
                                    <br>
                                    {{ \Carbon\Carbon::parse($saleRoom->date_checkout)->format('d/m/Y') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $allocation?->rate_sale ?? '-' }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $allocation?->tip_amount ?? '-' }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $allocation?->rate_net ?? '-' }}</td>
                            <td style="text-align:center; font-size:12px; vertical-align: middle;" rowspan="{{ $xb ? '2' : '1' }}">{{ $saleTip->note ?? '-' }}</td>
                        </tr>
                        @if($xb)
                            <tr>
                                <td colspan="2" style="text-align:right; font-size:16px; font-weight: bold; vertical-align: middle;">Extra Bed :</td>
                                <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $xb?->rate_sale ?? '-' }}</td>
                                <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $xb?->tip_amount ?? '-' }}</td>
                                <td style="text-align:center; font-size:12px; vertical-align: middle;">{{ $xb?->rate_net ?? '-' }}</td>
                            </tr>
                        @endif
                    @endforeach
                    <tr><td colspan="16"></td></tr>
                    <tr>
                        <td colspan="9"></td>
                        <th colspan="3" style="text-align:left;">Total Sold Room</th>
                        <td colspan="2" style="text-align:right;">{{ $data['summary']['total_sold_room'] }}</td>
                    </tr>

                    <tr>
                        <td colspan="9"></td>
                        <th colspan="3" style="text-align:left;">Total Not Sold Room</th>
                        <td colspan="2" style="text-align:right;">{{ $data['summary']['total_not_sold_room'] }}</td>
                    </tr>

                    <tr>
                        <td colspan="9"></td>
                        <th colspan="3" style="text-align:left;">Total Sale</th>
                        <td colspan="2" style="text-align:right;">
                            {{ number_format($data['summary']['total_sale'], 2) }}
                        </td>
                    </tr>

                    <tr>
                        <td colspan="9"></td>
                        <th colspan="3" style="text-align:left;">Total Commission</th>
                        <td colspan="2" style="text-align:right;">
                            {{ number_format($data['summary']['total_commision'], 2) }}
                        </td>
                    </tr>

                    <tr>
                        <td colspan="9"></td>
                        <th colspan="3" style="text-align:left;">Total Net Sale</th>
                        <td colspan="2" style="text-align:right;">
                            {{ number_format($data['summary']['total_net_sale'], 2) }}
                        </td>
                    </tr>

                    <tr>
                        <td colspan="9"></td>
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
