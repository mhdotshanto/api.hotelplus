<!doctype html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{$data['title']}}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="{{ asset('reports/css/bootstrap/bootstrap.min.css') }}" type="text/css" rel="stylesheet" >  
    <link href="{{ asset('reports/css/style.css') }}" type="text/css" rel="stylesheet" > 
        
  </head>

{{-- <table width="100%">
    <tr>
        <td width="50%" style="color:#0000BB; ">
            <img height="70px" src="{!! 'data:image/jpg;base64,'.base64_encode(file_get_contents('assets/ahits/img/report-logo.jpg')); !!}">
        </td>
        <td class="text-upper" width="50%" style="text-align: right; vertical-align: middle">
            <span>Print Time : {{date(config('ahits.datetimeformat'))}}</span>
        </td>
    </tr>
</table> --}}