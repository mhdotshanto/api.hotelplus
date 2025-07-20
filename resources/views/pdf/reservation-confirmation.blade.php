@php
   //dd($data,$data['saleRoom']);
@endphp

<div class="fo-12 top-5">
    <p class="text-center p-0 m-0"><strong>Reservation Confirmation:</strong> {{$data['saleRoom']->code}}</p>
    <p class="text-center p-0 m-0"><strong>Booking status:</strong> {{ ucfirst(strtolower($data['saleRoom']->status)) }}</p>
    <table class="fo-12">
        <tbody>
        <tr>
        </tr>
        <tr>
            <td class="p-0"><strong><span class="text-upper">{{$data['saleRoom']->guest_name_title}} {{$data['saleRoom']->guest_name_first}} {{$data['saleRoom']->guest_name_last}}</span></strong></td>
        </tr>
        <tr>
            <td class="p-0">Mobile : {{$data['saleRoom']->guest_mobile}}</td>
        </tr>
        <tr>
            <td class="p-0">Email : {{$data['saleRoom']->guest_email}}</td>
        </tr>
        <tr>
            <td class="p-0">Company : {{$data['saleRoom']->guest_company}}</td>
        </tr>
        </tbody>
    </table>
    <div class="mt-3">
        <p class="p-0 m-0">Dear <span class="text-upper">{{$data['saleRoom']->guest_name_title}} {{$data['saleRoom']->guest_name_first}} {{$data['saleRoom']->guest_name_last}}</span></p>
        <p class="p-0 m-0">Thank you very much for choosing {{config('ahits.property.name')}} at {{config('ahits.property.location')}}.
            Based on your request, we are pleased to confirm the booking as follows:</p>
    </div>
    <table class="table table-bordered mt-3 table-sm">
        <tr>
            <td width="18%">Guest Details</td>
            <td class="text-upper"><strong>{{$data['saleRoom']->guest_name_title}} {{$data['saleRoom']->guest_name_first}} {{$data['saleRoom']->guest_name_last}}</strong></td>
        </tr>

        <tr>
            <td>Arrival</td><td>{!! getDateTime(config('ahits.dateformat'),
            $data['saleRoom']->date_checkin)
            !!}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ETA: {!! getDateTime('h:i A',
            $data['saleRoom']->eta)
            !!}</td>
        </tr>
        <tr>
            <td>Departure</td><td>{!! getDateTime(config('ahits.dateformat'),
            $data['saleRoom']->date_checkout) !!}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ETD: {!! getDateTime('h:i A',
            $data['saleRoom']->etd)
            !!}</td>
        </tr>
        <tr>

            <td>Total Rooms</td>
            <td>{{$data['totalRoomQty']}} ROOMS
            {{-- @if($data['saleRoom']->sale_room_allocation->sum('is_xb') > 1)
                WITH {{$data['saleRoom']->sale_room_allocation->sum('is_xb')}} EXTRA BEDS
            @elseif($data['saleRoom']->sale_room_allocation->sum('is_xb') == 1)
                WITH {{$data['saleRoom']->sale_room_allocation->sum('is_xb')}} EXTRA BED
            @endif --}}
            </td>
        </tr>
        <tr>
            <td>Room Types</td>
            <td>
                {!! $data['categorySummaryString'] !!}
            </td>
        </tr>
        @if($data['saleRoom']->discount_amount > 0)
            <tr>
                <td>Discount</td>
                <td>{!! bdt_format($data['saleRoom']->discount_amount) !!}</td>
            </tr>
        @endif
        <tr>
            <td>Total Amount</td>
            <td>{!! bdt_format($data['saleRoom']->price_net) !!}</td>
        </tr>

        @if($data['saleRoom']->status == 'CANCELLED')
        <tr>
            <td>Cancellation Charge</td>
            <td>{!! bdt_format($data['saleRoom']->balance->cancellation_charge) !!}</td>
        </tr>
        @endif
        <tr>
            <td>Amount Paid</td>
            <td>{!! bdt_format($data['totalPaid']) !!}</td>
        </tr>
        <tr>
            <td>Amount Due</td>
            <td>{!! bdt_format($data['saleRoom']->price_net) !!}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="height: 20px;" width="50%">Booking Agent/Reference:<br>{{$data['saleRoom']->sale_agent->name}}</td>
            <td class="text-right" width="50%">Created By:<br>{{$data['saleRoom']->sale_user->name}}</td>
        </tr>

    </table>
    <span style="text-decoration:underline">Additional Information:</span>
    <ul style="margin-top: 2px">
        <li>Cancellation policy 7 days for FIT & 15 days for group booking written intimation prior to arrival date.<br>For guaranteed reservations, a one-night room charge per room shall be charged.</li>
        <li>Advance deposit is required at the time of check-in in case of cash payment.</li>
        <li><strong>Guest to provide copy of his/her NID or Passport at the time of Check in.</strong></li>
        <li><strong>Check in time 12 noon and check out time 11 am (early check in or late check out is subject to availability).</strong></li>
    </ul>
    <table class="table table-bordered table-sm table-padding">
        <tr>
            <td colspan="3" class="text-center"><strong>Bank Details</strong></td>
        </tr>
        <tr>
            <td width="50%">
                A/C Name: Cox Vacation<br>
                A/C No: 0091070124814<br>
                Eastern Bank
            </td>
            <td width="50%">
               Bkash Personal Account<br>01722-222005
            </td>
        </tr>
    </table>
    <div class="border fo-11">This is auto generated advance copy of your reservation, please acknowledge the same as note of your acceptance by return mail.</div>
    <p class="text-center font-italic font-bold">To enjoy the Best Available Rate, please contact with our Sales Office located at:</p>
    <table class="table-sm">
        <thead>
        </thead>
        <tbody>
        <tr>
            <td class="bg-secondary">Cox's Bazar Office</td>
            <td class="bg-secondary">Dhaka Office</td>
        </tr>
        <tr>
            <td width="40%"><strong>Hotline: 01877-721578</strong></td>
            <td width="60%"><strong>Hotline: 01713-222556, 01713-222557, 01877-721577</strong></td>
        </tr>
        <tr>
            <td>Plot: 24, Block: C, </td>
            <td>House: 01 (1st Floor), Road: 06, Sector: 3</td>
        </tr>
        <tr>
            <td>Kalatali, Cox's Bazar</td>
            <td>Uttara, Dhaka-1230</td>
        </tr>
        <tr>
            <td>Email: info@dhakahome.com</td>
            <td>Email: info@dhakahome.com</td>
        </tr>
        <tr>
            <td class="text-center bg-secondary" colspan="2">For Further Details Visit: www.dhakahome.com
            </td>
        </tr>
        </tbody>
    </table>
</div>