<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
class LedgerExport implements FromView
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
return view('reports.ledger.excel', [
'data' => $this->data,
'title' => $this->title,
]);
}   
}