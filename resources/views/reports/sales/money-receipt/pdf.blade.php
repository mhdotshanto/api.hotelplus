
@extends('reports.layouts.pdf.main')
@section('content')
<div class="fo-12">
    <p class="text-center p-0 m-0"><strong>Receipt# :</strong> {{$data->receipt_ref}}</p>
    <p class="text-center p-0 m-0"><strong>Reservation :</strong> {{$data->saleRoom->code}}</p>
    <p class="text-center p-0 m-0"><strong>Booking status:</strong>  {{strToUpper($data->saleRoom->status)}}</p>
    <table class="fo-12">
        <tbody>
        <tr>
        </tr>
        <tr>
            <td class="p-0">
                <strong>
                    <span class="text-upper">{{$data->saleRoom->guest_name_title}} {{$data->saleRoom->guest_name_first}} {{$data->saleRoom->guest_name_last}}</span>
                </strong>
            </td>
            <td class="p-0 text-right">
                <strong>Payment Date: </strong>{{$data->created_at->format('d/M/Y')}}
            </td>
        </tr>
        <tr>

            <td class="p-0">Mobile : {{$data->saleRoom->guest_mobile}}</td>
        </tr>
        <tr>
            <td class="p-0">Email : {{$data->saleRoom->guest_email}}</td>
        </tr>
        <tr>
            <td class="p-0">Company : {{$data->saleRoom->guest_company}}</td>
        </tr>
        </tbody>
    </table>
    <div class="mt-3">
        <p class="p-0 m-0">Dear <span class="text-upper">{{$data->saleRoom->guest_name_title}} {{$data->saleRoom->guest_name_first}} {{$data->saleRoom->guest_name_last}},</span></p>
        <p class="p-0 m-0">Payment of amount {!! numToWord($data->amount) !!} ONLY ({!! bdt_format($data->amount) !!}) received with thanks.</p>
    </div>
    <p>Payment Details</p>
    <table class="table table-bordered mt-3 table-sm">
        <thead>
            <tr>
                <th>Payment By</th>
                <th>Transaction</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>           
            <tr>
                <td>
                    {{ ucfirst($data->payment_method) }}
                </td>
                <td>{{ $data->note ?? '' }}</td>
                <td class="text-right">{!! bdt_format($data->amount) !!}</td>
            </tr>
        </tbody>
        {{-- <tfoot>
        <tr>
            <td colspan="2">Total</td>
            <td class="text-right">{!! bdt_format($data->sum('amount'))!!}</td>
        </tr>
        </tfoot> --}}
    </table>
    <p><strong>In Word : </strong>{!! numToWord($data->amount) !!} ONLY</p>
    <p><strong>Received By : </strong>{{$data->receiver->name??""}} </p>
</div>
@endsection