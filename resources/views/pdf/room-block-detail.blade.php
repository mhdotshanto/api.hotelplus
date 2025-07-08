<!DOCTYPE html>
<html>
<head>
    <title>Block Detail</title>
    <style>
        @page {
            header: page-header;
            footer: page-footer;
            margin-top: 40mm;    
            margin-bottom: 20mm;
        }

        body {
            font-family: sans-serif;
        }

        .content {
        }

        .footer-table {
            width: 100%;
            font-size: 12px;
        }

        .footer-table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
        @include('components.room-block-header')
        @include('components.room-block-footer')

        <div class="content">

            <table style="width: 100%;">
                <tr>
                    <td width="80px">ID</td>
                    <td width="8px">:</td>
                    <td> {{ $room_block->id }} </td>
                </tr>
                <tr>
                    <td width="80px">Blocked By </td>
                    <td width="8px">:</td>
                    <td> {{ $room_block->user->name }} </td>
                    <td style="text-align: right;">
                        <p class="text-right">Created at: <span>{{ $room_block->created_at->format('d/m/Y H:i:s') }}</span></p>
                    </td>
                </tr>
                <tr>
                    <td width="80px">NOTE</td>
                    <td width="8px">:</td>
                    <td> {{ $room_block->note }} </td>
                </tr>
            </table>

            <table style="border-collapse: collapse; width: 100%; margin-top:15px;">
                <thead>
                    <tr style="background-color: #f2f4f4;">
                        <th style="border: 1px solid #d5d8dc; padding: 4px; text-align: center; font-weight: 500; margin: 0;">DATE</th>
                        <th style="border: 1px solid #d5d8dc; padding: 4px; text-align: right; font-weight: 500; margin: 0;">ROOM NO</th>
                        <th style="border: 1px solid #d5d8dc; padding: 4px; text-align: left; font-weight: 500; margin: 0;">ROOM NAME</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($blocks) > 0)
                        <tr>
                            <td colspan="3" style="border: 1px solid #d5d8dc; padding: 4px; text-align: center;">BLOCK</td>
                        </tr>
                        @foreach($blocks as $index => $data)
                            @php
                                $blockDate = $data['date'];
                            @endphp
                            @foreach($data['rooms'] as $roomIndex => $room)
                                <tr>
                                    <td style="border: 1px solid #d5d8dc; padding: 4px; text-align: center;">{{ $blockDate }}</td>
                                    <td style="border: 1px solid #d5d8dc; padding: 4px; text-align: right;">{{ $room['name'] }}</td>
                                    <td style="border: 1px solid #d5d8dc; padding: 4px;">{{ $room['type']['name'] }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    @endif  

                    @if(count($unblocks) > 0)
                        <tr>
                            <td colSpan="3" style="border: 1px solid #d5d8dc; padding: 4px; text-align: center;">RELEASE</td>
                        </tr>
                        @foreach($unblocks as $index => $data)
                            @php
                                $blockDate = $data['date'];
                            @endphp
                            @foreach($data['rooms'] as $roomIndex => $room)
                                <tr>
                                    <td style="border: 1px solid #d5d8dc; padding: 4px; text-align: center;">{{ $blockDate }}</td>
                                    <td style="border: 1px solid #d5d8dc; padding: 4px; text-align: right;">{{ $room['name'] }}</td>
                                    <td style="border: 1px solid #d5d8dc; padding: 4px;">{{ $room['type']['name'] }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    @endif 
                </tbody>
            </table>
            
        </div>
</body>
</html>
