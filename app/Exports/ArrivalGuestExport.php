<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
class ArrivalGuestExport implements FromView
{
protected $data;
protected $title;

public function __construct(array $data, string $title)
{
$this->data = $data;
$this->title = $title;
}

public function view(): View
{
return view('reports.guest.inhouse.excel', [
'data' => $this->data,
'title' => $this->title,
]);
}   
}