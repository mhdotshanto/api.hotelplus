<?php
namespace App\Exports;

use App\Models\Guest;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
class AllGuestExport implements FromQuery, WithHeadings
{
    use Exportable;

    public function query()
    {
        // Don’t do Order::all() for big data
        return Guest::query()->select('id', 'first_name', 'last_name', 'email', 'mobile', 'created_at');
    }

    public function headings(): array
    {
        return ['ID', 'First Name', 'Last Name', 'Email', 'Mobile', 'Created At'];
    }
}