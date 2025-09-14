<link rel="stylesheet" href="{{asset('reports/css/pdf.min.css')}}">
<table width="100%">
    <tr>
        <td width="50%" style="color:#0000BB; ">
            <img height="70px" src="{!! 'data:image/jpg;base64,'.base64_encode(file_get_contents('assets/ahits/img/report-logo.jpg')); !!}">
        </td>
        <td class="text-upper" width="50%" style="text-align: right; vertical-align: middle">
            <span>Print Time : {{date(config('ahits.datetimeformat'))}}</span>
        </td>
    </tr>
</table>