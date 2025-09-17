<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title></title>

    <style>
        table,
        td,
        div,
        h1,
        p {
            font-family: Arial, sans-serif;
        }

        table,
        td {
            border: 2px solid #000000 !important;
            padding: 5px !important;
        }
    </style>

<body style="margin:0;padding:0;">
    <p style="font-size:16px;font-weight:bold">Hi <i> {{ $mailDetails['customer_name'] }}</i></p>
    <p style="font-size:16px;font-weight:bold">
        {{ $mailDetails['message'] }}
    </p>
    <div style="padding:5px ;font-size:16px; color:#000;">
        Thanks & Regards,<br >
       {{ $mailDetails['sender_name'] }}<br>
       Contact No: {{ $mailDetails['sender_mobile'] }}<br>
       Address: {{ $mailDetails['sender_address'] }}<br>
    </div>
</body>
</head>

</html>
