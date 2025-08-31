<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SaleRoom;
use App\Services\ReportServices\ReportService;
use App\Services\ReportServices\MpdfService;
use Carbon\Carbon;
use App\Exports\LedgerExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    protected $mpdfService;
    protected $reportService;
    public function __construct(MpdfService $mpdfService, ReportService $reportService)
    {
        $this->mpdfService = $mpdfService;
        $this->reportService = $reportService;
    }
    public function ledgerReport(Request $request)
    {
       // dd($request->all());
        // try {
        $validated = $request->validate([
            'token' => 'required|string',
            'output' => 'string',
        ]);
        //dd("testing");
        if ($validated) {
            $token = $validated['token'];
            $output = $validated['output'];
        } else {
            return response()->json([
                'message' => 'Token Not Found',
            ], 400);
        };
        //dd($output);
        $data = $this->reportService->getledgerData($token);
        //dd($data);
        $name = "Ledger Report ". $data['saleRoom']->code??"";
        if ($output === 'html') {
            if ($data) {
                return view('reports.ledger.html')->with(['data' => $data, 'title' => $name]);
            }
        } elseif ($output === "excel") {
            if ($data) {
                return Excel::download(
                    new LedgerExport($data, $name),
                    "{$name}.xlsx"
                );
            }
        } elseif($output=="pdf") {            
            $html = view('reports.ledger.pdf')->with(['data' => $data, 'title' => $name])->render();
            $header = view('reports.layouts.pdf.header')->render();
            $footer = view('reports.layouts.pdf.footer')->render();
            $this->mpdfService->mpdf($name);
            $mpdf = $this->mpdfService->getMpdf();
            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);
            $mpdf->WriteHTML($html);
            $mpdf->Output("{$data['saleRoom']->code}.pdf", 'I');
        }
        
    }

    public function printReservationDetail(Request $request)
    {
        // try {
            $validated = $request->validate([
                'token' => 'required|string'
            ]);

            if ($validated) {
                $token = $validated['token'];
            } else {
                return response()->json([
                    'message' => 'Token Not Found',
                ], 400);
            };
           
            //dd($output);
            $data = $this->reportService->getReservationDetail($token);

            if ($data) {
                $html = view('reports.reservation.pdf')->with(['data' => $data, 'title' => $data['name']])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();

                $this->mpdfService->mpdf($data['name']);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$data['saleRoom']->code}.pdf", 'I');
            }
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'message' => 'Something Went Wrong!',
        //         'error' => $e->getMessage()
        //     ], 422);
        // }
    }
}
