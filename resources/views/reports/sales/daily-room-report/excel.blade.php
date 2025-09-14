@extends('reports.layouts.excel.main')
@section('content')
<div class="fo-12 top-5">         
    <div class="mt-3">
        <table class="table border no-column-border">
        <tbody>
            <tr class="daily-room-header">
                <th>ROOM#</th>
                <th>CATEGORY</th>
                <th>RESV AND STATUS</th>                
                <th colspan="2" style="text-align:center;">GUEST INFO</th>
                <th>REFERENCE</th>
                <th>CREATED BY</th>
                <th>PERIOD</th>
                <th colspan="3" style="text-align:center;">PRICE</th>
                <th>TIP NOTE</th>
            </tr>
            <tr class="daily-room-header">
            <th>&nbsp;</th>
            <th>&nbsp;</th>
            <th>&nbsp;</th>
            <th>NAME AND CONTACT</th>
            <th>ADDRESS</th>
            <th>&nbsp;</th>
            <th>&nbsp;</th>
            <th>&nbsp;</th>
            <th>ROOM RENT</th>
            <th>COMISSION</th>
            <th>NET RENT</th>
            <th></th>
        </tr>
            @foreach ($data['roomData'] as $item)
            @if($item['status'] ==='guaranteed' || $item['status'] ==='checkedin' || $item['status'] ==='checkedout')
                <tr class="ledger-details sold-room"  >
                    <td width="" style="text-align:left">{{$item['room_name']}}</td>
                    <td width="" style="text-align:left">{{$item['room_category_name']}}</td>
                    <td width="" style="text-align:left">{{$item['code']}} {{$item['status']}}</td>                    
                    <td width="" style="text-align:left">{{$item['guest_name']}} {{$item['guest_mobile']}}</td>
                    <td width="" style="text-align:left">{{$item['guest_address']}}</td>
                    <td width="" style="text-align:left">{{$item['reference_name']}}</td>
                    <td width="" style="text-align:left">{{$item['created_by_name']}}</td>
                    <td width="" style="text-align:left">{{$item['period']}} </td>
                    <td width="" style="text-align:right">{{bdt_format($item['room_rent'])}}</td>
                    <td width="" style="text-align:right">{{bdt_format($item['commision'])}}</td>
                    <td width="" style="text-align:right">{{bdt_format($item['price_net'])}}</td>
                    <td></td>      
                </tr> 
            @elseif($item['status'] ==='tentative' || $item['status'] ==='postponed')
            <tr class="ledger-details" >
                    <td width="" style="text-align:left">{{$item['room_name']}}</td>
                    <td width="" style="text-align:left">{{$item['room_category_name']}}</td>
                    <td width="" style="text-align:left">{{$item['code']}} {{$item['status']}}</td>                    
                    <td width="" style="text-align:left">{{$item['guest_name']}} {{$item['guest_mobile']}}</td>
                    <td width="" style="text-align:left">{{$item['guest_address']}}</td>
                    <td width="" style="text-align:left">{{$item['reference_name']}}</td>
                    <td width="" style="text-align:left">{{$item['created_by_name']}}</td>
                    <td width="" style="text-align:left">{{$item['period']}} </td>
                    <td width="" style="text-align:right">{{bdt_format($item['room_rent'])}}</td>
                    <td width="" style="text-align:right">{{bdt_format($item['commision'])}}</td>
                    <td width="" style="text-align:right">{{bdt_format($item['price_net'])}}</td>
                    <td></td>      
                </tr> 
             @else
                <tr class="ledger-details">
                    <td width="" style="text-align:left">{{$item['room_name']}}</td>
                    <td width="" style="text-align:left">{{$item['room_category_name']}}</td>
                    <td width="" colspan="10" style="text-align:center">---Not Sold---</td>  
                </tr> 
            @endif               
            @endforeach                   
        </tbody>
    </table>
    </div>  
   
        <table class="table">
            <tbody style="font-weight:bold;font-size:20px;">
                {{-- <tr>
                  <td width="50%">Total Not Sold Room</td><td width="20%">:</td><td width="30%" style="text-align:right"> {{$data['summary']['total_not_sold_room']}}</td>  
                </tr> --}}
                <tr>
                  <td width="50%">Total Sold Room</td><td width="20%">:</td><td width="30%" style="text-align:right"> {{$data['summary']['total_sold_room']}}</td>  
                </tr>
                <tr>
                  <td width="50%">Total Sale</td><td width="20%">:</td><td width="30%" style="text-align:right">{{$data['summary']['total_sale']}}</td>  
                </tr>
                <tr>
                  <td width="50%">Total Comission</td><td width="20%">:</td><td width="30%" style="text-align:right">{{$data['summary']['total_commision']}}</td>  
                </tr>
                <tr>
                  <td width="50%">Total Net Sale</td><td width="20%">:</td><td width="30%" style="text-align:right">{{$data['summary']['total_net_sale']}}</td>  
                </tr>
                <tr style="border-top:2px solid black;">
                  <td width="50%">Total Income</td><td width="20%">:</td><td width="30%" style="text-align:right">{{$data['summary']['total_income']}}</td>  
                </tr>               
            </tbody>
        </table>
   
</div>
@endsection