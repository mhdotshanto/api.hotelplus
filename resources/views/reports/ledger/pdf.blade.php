@extends('reports.layouts.pdf.main')
@section('content')
<div class="fo-12 top-5">
    <p class="text-center p-0 m-0 fo-12" style="font-size:22px"><b>Ledger:</b> {{$data['saleRoom']->code}}</p>
    <p class="text-center p-0 m-0"><strong>Reservation Confirmation:</strong> {{$data['saleRoom']->code}}</p>
    <p class="text-center p-0 m-0"><strong>Booking status:</strong> {{ ucfirst(strtolower($data['saleRoom']->status)) }}</p>

    <table class="fo-12 mt-2">
        <tbody>
        <tr>
            <td class="p-0"><strong><span class="text-upper">{{$data['saleRoom']->guest_name_title}} {{$data['saleRoom']->guest_name_first}} {{$data['saleRoom']->guest_name_last}}</span></strong></td>
        </tr>
        <tr>
            <td class="p-0">Mobile : {{$data['saleRoom']->guest_mobile}}</td>
        </tr>
        @if(!empty($data['saleRoom']->guest_email))
        <tr>
            <td class="p-0">Email : {{$data['saleRoom']->guest_email}}</td>
        </tr>
        @endif
        @if(!empty($data['saleRoom']->guest_company))
        <tr>
            <td class="p-0">Company : {{$data['saleRoom']->guest_company}}</td>
        </tr>
        @endif
        </tbody>
    </table>

    <table class="table table-bordered mt-3 table-sm">
        <tr>
            <td width="20%">Guest Details</td>
            <td class="text-upper"><strong>{{$data['saleRoom']->guest_name_title}} {{$data['saleRoom']->guest_name_first}} {{$data['saleRoom']->guest_name_last}}</strong></td>
        </tr>
        <tr>
            <td>Arrival</td>
            <td>{!! getDateTime(config('ahits.dateformat'), $data['saleRoom']->date_checkin) !!}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ETA: {!! getDateTime('h:i A', $data['saleRoom']->eta) !!}</td>
        </tr>
        <tr>
            <td>Departure</td>
            <td>{!! getDateTime(config('ahits.dateformat'), $data['saleRoom']->date_checkout) !!}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ETD: {!! getDateTime('h:i A', $data['saleRoom']->etd) !!}</td>
        </tr>
        <tr>
            <td>Total Rooms</td>
            <td>{{$data['totalRoomQty']}} ROOMS
            @if(isset($data['totalXbQty']) && $data['totalXbQty'] > 1)
                WITH {{$data['totalXbQty']}} EXTRA BEDS
            @elseif(isset($data['totalXbQty']) && $data['totalXbQty'] == 1)
                WITH {{$data['totalXbQty']}} EXTRA BED
            @endif
            </td>
        </tr>
        @if(!empty($data['categorySummaryStringWithoutXB']))
        <tr>
            <td>Room Types</td>
            <td>{!! $data['categorySummaryStringWithoutXB'] !!}</td>
        </tr>
        @endif
    </table>

    <div class="mt-3">
        <table class="table table-bordered border-primary">
        <tbody>
            <tr class="ledger-header">
                <th>Date</th>
                <th>Note</th>
                <th>Debit</th>
                <th>Credit</th>
            </tr>
            @foreach ($data['ledger_data'] as $item)
                @php
                    $noteText = $item->ledgerNote->name ?? $item->voucher->note ?? $item->head->name ?? null;
                @endphp
                <tr class="ledger-details">
                <td width="20%" style="text-align:center">{{$item->date}}</td>
                <td width="40%" style="text-align:center">{{ !empty($noteText) ? $noteText : '----------' }}</td>
                <td width="20%" style="text-align:right">{{bdt_format($item->debit)}}</td>
                <td width="20%" style="text-align:right">{{bdt_format($item->credit)}}</td>
                </tr>                
            @endforeach
                <tr>
                    <td colspan="2" style="text-align:right">Total:</td>
                    <td style="text-align:right">{{bdt_format($data['ledger_summary']['total_debit'])}}</td>
                    <td style="text-align:right">{{bdt_format($data['ledger_summary']['total_credit'])}}</td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:right">Total Due:</td>                    
                    <td colspan="2" style="text-align:right">{{bdt_format($data['ledger_summary']['total_due'])}}</td>
                </tr>
        </tbody>
    </table>
    </div>  
   
</div>
@endsection
