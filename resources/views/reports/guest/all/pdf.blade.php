@extends('reports.layouts.pdf.main')
@section('content')
<div class="fo-12 top-5">
    <p class="text-center p-0 m-0 fo-12" style="font-size:22px">{{ strToUpper($data['title'])}}</p> 
    <p class="text-center p-0 m-0 fo-12" style="font-size:18px"><strong>DATE:</strong> {{ \Carbon\Carbon::parse(now())->format('d/m/Y') }}</p>
    <div class="mt-3">
        <table class="table table-bordered border-primary">
        <tbody>
            <tr class="ledger-header">
                 <th>SL#</th>
                <th>GUEST</th>
                <th>CONTACT</th>
                <th>RES</th>
                <th>RES PERIOD</th>
                <th>ROOMS</th>
                <th>PRICE</th>
                <th>DISCOUNT</th>
                <th>NET PRICE</th>
                <th>COLLECTION</th>
                <th>REFUND</th>
                <th>BALANCE</th>
            </tr>
            @foreach ($data['roomSaleData'] as $item)
                <tr class="ledger-details">
                <td tyle="text-align:center">{{$loop->iteration}}</td>
                <td  style="text-align:left">{{$item->guest_name_title}} {{$item->guest_name_first}} {{$item->guest_name_last}}</td>
                <td  style="text-align:left">{{$item->guest_mobile}}</td>
                <td  style="text-align:left">{{$item->code}}</td>
                <td  style="text-align:left">{{\Carbon\Carbon::parse($item->date_checkin)->format('d/m/Y')}} - {{\Carbon\Carbon::parse($item->date_checkout)->format('d/m/Y')}}</td>
                <td style="text-align:left">{{$item->room_numbers}}<br>Total:{{$item->total_rooms}}                    
                <td  style="text-align:right">{{bdt_format($item->price_gross)}}</td>
                <td  style="text-align:right">{{bdt_format($item->discount_amount)}}</td>
                <td  style="text-align:right">{{bdt_format($item->price_net)}}</td>
                <td  style="text-align:right">{{bdt_format($item->price_net)}}</td>
                <td style="text-align:right">0.00</td>
                <td  style="text-align:right">{{bdt_format($item->price_net)}}</td>
            </tr>                
            @endforeach
             <tr class="fo-12 ledger-header" style="font-weight: bold;font-size: 16px;">
                <td colspan="6" style="text-align:left"><strong>Total:</strong></td>
                <td style="text-align:right"><strong>{{bdt_format($data['total_gross_price'])}}</strong></td>
                <td style="text-align:right"><strong>{{bdt_format($data['total_discount'])}}</strong></td>
                <td style="text-align:right"><strong>{{bdt_format($data['total_net_price'])}}</strong></td>
                <td style="text-align:right"><strong>{{bdt_format($data['total_collection'])}}</strong></td>
                <td style="text-align:right"><strong>{{bdt_format($data['total_refund'])}}</strong></td>
                <td style="text-align:right"><strong>{{bdt_format($data['total_balance'])}}</strong></td>
            </tr>                   
        </tbody>
    </table>
    </div>  
   
</div>
@endsection