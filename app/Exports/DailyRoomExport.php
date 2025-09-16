<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
class DailyRoomExport implements FromView
{
protected $data;

public function __construct(array $data, string $title)
{
$this->data = $data;
$this->title = $data['title'];
}

public function view(): View
{
return view('reports.sales.daily-room-report.excel', [
'data' => $this->data
]);
}   
}