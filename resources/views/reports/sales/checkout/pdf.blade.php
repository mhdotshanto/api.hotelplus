@extends('reports.layouts.pdf.main')
@section('content')
<div class="fo-12 top-5">
    <p class="text-center p-0 m-0 fo-12" style="font-size:22px">CHECKOUT REPORT</p>
   <p class="text-center p-0 m-0 fo-12" style="font-size:18px"><strong>DATE:</strong> {{ $data['from_date'] }} TO {{ $data['to_date'] }}</p>
   <p class="text-center p-0 m-0 fo-12" style="font-size:16px"><strong>STAFF:</strong> {{ $data['sale_agents'] }}</p>
    <div class="mt-3">
        <table class="table table-bordered border-primary">
        <tbody>
            <tr class="ledger-header">
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
            @foreach ($data['checkinData'] as $item)
                <tr class="ledger-details">
                <td width="20%" style="text-align:left">{{$item->code}}</td>
                <td width="20%">{{$item->date}}</td>
                <td width="20%" style="text-align:left">{{$item->date_checkin}} - {{$item->date_checkout}}</td>
                <td width="20%" style="text-align:left"><p>{{$item->guest_name_title}} {{$item->guest_name_first}} {{$item->guest_name_last}}</p><p>
                    {{$item->guest_mobile}}</p></td>
                    <td width="20%" style="text-align:left">{{$item->sale_agent?$item->sale_agent->name:""}}</td>
                    <td width="20%" style="text-align:center">{{$item->room_qty}}</td>
                    <td width="20%" style="text-align:right">{{$item->price_gross}}</td>
                    <td width="20%" style="text-align:right">{{$item->price_net}}</td>
                    <td width="20%" style="text-align:right">0.00</td>
                    <td width="20%" style="text-align:right">0.00</td>
                </tr>                
            @endforeach
            <tr>
                    <td colspan="5" style="text-align:left">Total:</td>
                    <td style="text-align:center">{{$data['total_rooms']}}</td>
                    <td style="text-align:right">{{bdt_format($data['total_gross_price'])}}</td>
                    <td style="text-align:right">{{bdt_format($data['total_net_price'])}}</td>
                    <td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td>
                </tr>                
        </tbody>
    </table>
    </div>  
   
</div>
@endsection