<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
class LedgerExportWithLogo implements FromView, WithDrawings
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
    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Report Logo');
        $drawing->setPath(public_path('assets/ahits/img/report-logo.jpg')); // file path, not base64
        $drawing->setHeight(20);
        $drawing->setCoordinates('A2'); // place it at A1 cell

        return $drawing;
    }
}