@extends('reports.layouts.excel.main')
@section('content')
<div class="fo-12 top-5">
    <div class="mt-3">
        <table class="table table-bordered border-primary">
        <tbody>
            <tr class="ledger-header">
                <th>Date</th>
                <th>Account Head</th>
                <th>Note</th>
                <th>Debit</th>
                <th>Credit</th>
            </tr>
            @foreach ($data['ledger_data'] as $item)
                @php
                    $accHeadName = $item->head?->name 
                        ?? $item->voucher?->head?->name 
                        ?? $item->bank?->name 
                        ?? $item->voucher?->bank?->name 
                        ?? $item->party?->name 
                        ?? $item->voucher?->party?->name 
                        ?? 'N/A';
                    $noteText = $item->ledgerNote?->name ?? $item->voucher?->note ?? $item->note ?? 'N/A';
                @endphp
                <tr class="ledger-details">
                <td width="15%" style="text-align:center">{{$item->date}}</td>
                <td width="25%">{{ $accHeadName }}</td>
                <td width="30%">{{ $noteText }}</td>
                <td width="15%" style="text-align:right">{{bdt_format($item->debit)}}</td>
                <td width="15%" style="text-align:right">{{bdt_format($item->credit)}}</td>
                </tr>                
            @endforeach
                <tr>
                    <td colspan="3" style="text-align:right">Total:</td>
                    <td style="text-align:right">{{bdt_format($data['ledger_summary']['total_debit'])}}</td>
                    <td style="text-align:right">{{bdt_format($data['ledger_summary']['total_credit'])}}</td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align:right">Total Due:</td>                    
                    <td colspan="2" style="text-align:right">{{bdt_format($data['ledger_summary']['total_due'])}}</td>
                </tr>
        </tbody>
    </table>
    </div>  
   
</div>
@endsection