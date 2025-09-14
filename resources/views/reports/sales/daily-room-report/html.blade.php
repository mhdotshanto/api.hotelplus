@extends('reports.layouts.html.main')
@section('content')
<div class="fo-12 top-5">
    <p class="text-center p-0 m-0 fo-12" style="font-size:22px">DAILY ROOM REPORT</p>
   <p class="text-center p-0 m-0 fo-12" style="font-size:18px"><strong>DATE:</strong> {{ $data['date'] }}</p>
   
    <div class="mt-3">
    <table class="table border no-column-border">
        <tbody>
            <tr class="daily-room-header">
                <th>ROOM#</th>
                <th>CATEGORY</th>
                <th>RESV & STATUS</th>                
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
            <th>NAME & CONTACT</th>
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
                <tr class="sold-room"  >
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
    <div class="d-flex justify-content-end mt-4">
      <!-- First table -->
      <div class="me-4">
        <table class="table table-bordered table-sm shadow-sm align-items-end">
          <tbody style="font-weight:bold;font-size:18px;">
            <tr>
              <td>Total Sold Room</td>
              <td class="text-end">{{ $data['summary']['total_sold_room'] }}</td>
            </tr>
            <tr>
              <td>Total Sale</td>
              <td class="text-end">{{ $data['summary']['total_sale'] }}</td>
            </tr>
            <tr>
              <td>Total Commission</td>
              <td class="text-end">{{ $data['summary']['total_commision'] }}</td>
            </tr>
            <tr>
              <td>Total Net Sale</td>
              <td class="text-end">{{ $data['summary']['total_net_sale'] }}</td>
            </tr>
            <tr class="table-secondary border-top border-dark">
              <td>Total Income</td>
              <td class="text-end">{{ $data['summary']['total_income'] }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Second table -->
      <div>
        <table class="table table-bordered table-sm shadow-sm">
          <tbody style="font-weight:bold;font-size:18px;">            
            <tr>
              <td >Cash in Hand</td>
              <td class="text-end">0.00</td>
            </tr>
            <tr>
              <td>Cash in Bank</td>
              <td class="text-end">0.00</td>
            </tr>
            <tr>
              <td>Cash in Bkash</td>
              <td class="text-end">{{ $data['summary']['total_commision'] }}</td>
            </tr>
            <tr class="table-secondary border-top border-dark">
              <td>Total Balance</td>
              <td class="text-end">0.00</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    
   
</div>
@endsection