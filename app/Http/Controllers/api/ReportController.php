<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SaleRoom;
use App\Services\ReportServices\ReportService;
use App\Services\ReportServices\MpdfService;
use App\Exports\LedgerExport;
use App\Exports\CheckinExport;
use App\Exports\CheckoutExport;
use App\Exports\DailyRoomExport;
use App\Exports\InhouseGuestExport;
use App\Exports\ArrivalGuestExport;
use App\Exports\AllGuestExport;
use App\Exports\GuestByDateExport;
use App\Models\Guest;
use App\Services\CRM\EmailServices as CRMEmailServices;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\EmailServices;
use App\Traits\Formatter;
class ReportController extends Controller
{
    use Formatter;
    protected $mpdfService;
    protected $reportService;
    protected $emailService;
    public function __construct(MpdfService $mpdfService, ReportService $reportService,EmailServices $emailServices)
    {
        $this->mpdfService = $mpdfService;
        $this->reportService = $reportService;
        $this->emailService = $emailServices;
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
    public function checkinReport(Request $request)
    {
       // dd($request->all());
        try {
            $validated = $request->validate([           
                'output' => 'required|string',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'references' => 'required|array',            
            ]);
            $name = "Checkin Report";
            if ($validated) {
                $output = $validated['output'];
                $data = $this->reportService->getCheckinData($validated);
                $data['routes']['pdf'] = route(
                    'checkedin',
                    [
                        'output' => 'pdf',
                        'date_from' => $validated['date_from'],
                        'date_to' => $validated['date_to'],
                        'references' => $validated['references'],
                    ]
                );
                $data['routes']['excel'] = route(
                    'checkedin',
                    [
                        'output' => 'excel',
                        'date_from' => $validated['date_from'],
                        'date_to' => $validated['date_to'],
                        'references' => $validated['references'],
                    ]
                );
                $data['title'] = $name;
            } else {
                return response()->json([
                    'message' => 'Validation Failed',
                ], 400);
            };
        
             
            if ($output === 'html') {
                if ($data) {
                    return view('reports.sales.checkin.html')->with(['data' => $data]);
                }
            } elseif ($output === "excel") {
                if ($data) {
                    return Excel::download(
                        new CheckinExport($data, $name),
                        "{$name}.xlsx"
                    );
                }
            } elseif($output=="pdf") {            
                $html = view('reports.sales.checkin.pdf')->with(['data' => $data])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($name);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$name}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
        
    }
    public function printReservationDetail(Request $request)
    {
         try {
            $name = "Reservation Confirmation";
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
                $html = view('reports.reservation.pdf')->with(['data' => $data, 'title' => $name])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();

                $this->mpdfService->mpdf($name);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$data['saleRoom']->code}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
    }
    public function checkoutReport(Request $request)
    {
       // dd($request->all());
        try {
            $validated = $request->validate([           
                'output' => 'required|string',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'references' => 'required|array',            
            ]);
            $name = "Checkout Report"; 
            if ($validated) {
                $output = $validated['output'];
                $data = $this->reportService->getCheckoutData($validated);
                $data['routes']['pdf'] = route(
                    'checkedout',
                    [
                        'output' => 'pdf',
                        'date_from' => $validated['date_from'],
                        'date_to' => $validated['date_to'],
                        'references' => $validated['references'],
                    ]
                );
                $data['routes']['excel'] = route(
                    'checkedout',
                    [
                        'output' => 'excel',
                        'date_from' => $validated['date_from'],
                        'date_to' => $validated['date_to'],
                        'references' => $validated['references'],
                    ]
                );
                $data['title'] = $name;
            } else {
                return response()->json([
                    'message' => 'Validation Failed',
                ], 400);
            };
        
            
            if ($output === 'html') {
                if ($data) {
                    return view('reports.sales.checkout.html')->with(['data' => $data]);
                }
            } elseif ($output === "excel") {
                if ($data) {
                    return Excel::download(
                        new CheckoutExport($data, $name),
                        "{$name}.xlsx"
                    );
                }
            } elseif($output=="pdf") {            
                $html = view('reports.sales.checkout.pdf')->with(['data' => $data])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($name);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$name}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
        
    }
    public function dailyRoomReport(Request $request)
    {
       // dd($request->all());
        try {
            $validated = $request->validate([           
                'output' => 'required|string',
                'date' => 'required|date', 
                'roomType'=>'required|string',      
            ]);
            if ($validated) {
                $output = $validated['output'];
                $data = $this->reportService->getDailyRoomData($validated);
                $data['routes']['pdf']= route(
                    'daily-room-report',[
                        'output' => 'pdf',
                        'date' => $validated['date'],
                        'roomType' => $validated['roomType'],
                    ]
                );
                $data['routes']['excel'] = route(
                    'daily-room-report',
                    [
                        'output' => 'excel',
                        'date' => $validated['date'],
                        'roomType' => $validated['roomType'],
                    ]
                );
                $data['title'] = "Daily Room Report";
                
                //dd($data);
            } else {
                return response()->json([
                    'message' => 'Validation Failed',
                ], 400);
            };
        
            $name = "Daily Room Report"; 
            if ($output === 'html') {
                if ($data) {
                    return view('reports.sales.daily-room-report.html')->with(['data' => $data]);
                }
            } elseif ($output === "excel") {
                if ($data) {
                    return Excel::download(
                        new DailyRoomExport($data, $name),
                        "{$name}.xlsx"
                    );
                }
            } elseif($output=="pdf") {    
                //dd($data);        
                $html = view('reports.sales.daily-room-report.pdf')->with(['data' => $data, 'title' => $name])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($name);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$name}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
        
    }
    public function inhouseGuestReport(Request $request)
    {        
        try {
            $validated = $request->validate([           
                'output' => 'required|string',
                'reportType'=>'required|string',      
            ]);
            if ($validated) {
                $reportType = $validated['reportType'];
                 if(isset($reportType)){
                    if($reportType=='inhouseGuest'){
                    $report_title = "INHOUSE GUEST REPORT";
                    } elseif($reportType=='checkinMissed'){
                        $report_title = "CHECKIN MISSED GUEST REPORT";
                    }elseif($reportType=='checkoutMissed'){
                        $report_title = "CHECKOUT MISSED GUEST REPORT";
                    }
                }
                $output = $validated['output'];
                $data = $this->reportService->getInhouseGuestData($validated);
                //dd($data);
                $data['routes']['pdf']= route(
                    'inhouse-guest-report',[
                        'output' => 'pdf',
                        'reportType' => $validated['reportType'],
                    ]
                );
                $data['routes']['excel'] = route(
                    'inhouse-guest-report',
                    [
                        'output' => 'excel',
                        'reportType' => $validated['reportType'],
                    ]
                );
                $data['title'] = $report_title;
            } else {
                return response()->json([
                    'message' => 'Validation Failed',
                ], 400);
            };
        
            //$name = "Inhouse Guest Report"; 
            if ($output === 'html') {
                if ($data) {
                    return view('reports.guest.inhouse.html')->with(['data' => $data]);
                }
            } elseif ($output === "excel") {
                if ($data) {
                    return Excel::download(
                        new InhouseGuestExport($data, $report_title),
                        "{$report_title}.xlsx"
                    );
                }
            } elseif($output=="pdf") {            
                $html = view('reports.guest.inhouse.pdf')->with(['data' => $data, 'title' => $report_title])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($report_title);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$report_title}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
        
    }

    public function arrivalGuestReport(Request $request)
    {
            $validated = $request->validate([
                'output' => 'required|string',
            ]);
            $name = "Arrival Report";
            if ($validated) {
                $output = $validated['output'];
                $data = $this->reportService->getArrivalGuestData($validated);
                $data['routes']['pdf'] = route(
                    'arrival-guest-report',
                    [
                        'output' => 'pdf',
                    ]
                );
                $data['routes']['excel'] = route(
                    'arrival-guest-report',
                    [
                        'output' => 'excel',
                    ]
                );
                $data['title'] = $name;
            } else {
                return response()->json([
                    'message' => 'Validation Failed',
                ], 400);
            };
        try {
            if ($output === 'html') {
                if ($data) {
                    return view('reports.guest.arrival.html')->with(['data' => $data]);
                }
            } elseif ($output === "excel") {
                if ($data) {
                    return Excel::download(
                        new ArrivalGuestExport($data, $name),
                        "{$name}.xlsx"
                    );
                }
            } elseif ($output == "pdf") {
                $html = view('reports.guest.arrival.pdf')->with(['data' => $data])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($name);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$name}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
    }
    public function guestReportByDate(Request $request)
    {
        //dd($request->all());
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'output' => 'required|string',
            'reportType' => 'required|string',
        ]);        
        if ($validated) {
            $reportType = $validated['reportType'];
            if(isset($reportType)){
                if($reportType=='checkedin'){
                    $report_name = "GUEST REPORT BY CHECKED IN DATE";
                }elseif($reportType=='checkedout'){
                    $report_name = "GUEST REPORT BY CHECKED OUT DATE";
                }
            }
            $report_title = "GUEST REPORT BY DATE";            
            $output = $validated['output'];
            $data = $this->reportService->getGuestByDate($validated);
            $data['routes']['pdf'] = route(
                'guest-report-by-date',
                [
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'reportType' => $validated['reportType'],
                    'output' => 'pdf',
                ]
            );
            $data['routes']['excel'] = route(
                'guest-report-by-date',
                [
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'reportType' => $validated['reportType'],
                    'output' => 'excel',
                ]
            );
            $data['title'] = $report_title;
            $data['date_from'] = $validated['date_from'];
            $data['date_to'] = $validated['date_to'];
        } else {
            return response()->json([
                'message' => 'Validation Failed',
            ], 400);
        };
        try {
            if ($output === 'html') {
                if ($data) {
                    return view('reports.guest.byDate.html')->with(['data' => $data]);
                }
            } elseif ($output === "excel") {
                if ($data) {
                    return Excel::download(
                        new GuestByDateExport($data, $report_name),
                        "{$report_name}.xlsx"
                    );
                }
            } elseif ($output == "pdf") {
                $html = view('reports.guest.byDate.pdf')->with(['data' => $data])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($report_title);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$report_name}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
    }
    public function allGuestReport(Request $request)
    {
        $validated = $request->validate([
            'output' => 'required|string',
        ]);
        $name = "All Guests Report";
        //dd($validated, $name);
        if ($validated) {
            $output = $validated['output'];
            $data = $this->reportService->getAllGuestData($validated);
            $data['routes']['pdf'] = route(
                'all-guest-report',
                [
                    'output' => 'pdf',
                ]
            );
            $data['routes']['excel'] = route(
                'all-guest-report',
                [
                    'output' => 'excel',
                ]
            );
            $data['title'] = $name;
        } else {
            return response()->json([
                'message' => 'Validation Failed',
            ], 400);
        };
        try {
            if ($output === 'html') {
                if ($data) {
                    return view('reports.guest.all.html')->with(['data' => $data]);
                }
            } elseif ($output === "excel") {
                if ($data) {
                    return (new AllGuestExport)->download('allGuests.xlsx');
                    // return Excel::download(
                    //     new AllGuestExport($data, $name),
                    //     "{$name}.xlsx"
                    // );
                }
            } elseif ($output == "pdf") {
                $this->handleLargePdf();
                // $html = view('reports.guest.all.pdf')->with(['data' => $data])->render();
                // $header = view('reports.layouts.pdf.header')->render();
                // $footer = view('reports.layouts.pdf.footer')->render();
                // $this->mpdfService->mpdf($name);
                // $mpdf = $this->mpdfService->getMpdf();
                // $mpdf->SetHTMLHeader($header);
                // $mpdf->SetHTMLFooter($footer);
                // $mpdf->WriteHTML($html);
                // $mpdf->Output("{$name}.pdf", 'I');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ], 422);
        }
    }
    public function printMoneyReceipt($id)
    {
        //dd($id);
        $data = $this->reportService->getMoneyReceiptData($id);        
        $name = "Money Receipt - {$data->saleRoom->code}";
        if ($data) {
            $html = view('reports.sales.money-receipt.pdf')->with(['data' => $data, 'title' => $name])->render();
            $header = view('reports.layouts.pdf.header')->render();
            $footer = view('reports.layouts.pdf.footer')->render();

            $this->mpdfService->mpdf($name);
            $mpdf = $this->mpdfService->getMpdf();
            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);
            $mpdf->WriteHTML($html);
            $mpdf->Output("{$data->saleRoom->code}_Money_Receipt.pdf", 'I');
            // return response($mpdf->Output("money-receipt-{$id}.pdf", "S"))
            //     ->header('Content-Type', 'application/pdf')
            //     ->header('Content-Disposition', 'attachment; filename="money-receipt-' . $id . '.pdf"')
            //     ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }
    }
    public function printMoneyReceiptAuthenticated($id)
    {
        $data = $this->reportService->getMoneyReceiptData($id);
        $name = "Money Receipt - {$data->saleRoom->code}";
        if ($data) {
            $html = view('reports.sales.money-receipt.pdf')->with(['data' => $data, 'title' => $name])->render();
            $header = view('reports.layouts.pdf.header')->render();
            $footer = view('reports.layouts.pdf.footer')->render();

            $this->mpdfService->mpdf($name);
            $mpdf = $this->mpdfService->getMpdf();
            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);
            $mpdf->WriteHTML($html);
            return response($mpdf->Output("money-receipt-authenticated-{$id}.pdf", "S"))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="money-receipt-authenticated' . $id . '.pdf"')->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }
    }
    public function handleLargePdf()
    {
        $this->mpdfService->mpdf("Large PDF");
        $mpdf = $this->mpdfService->getMpdf();
        $mpdf->SetHTMLHeader(view('reports.layouts.pdf.header')->render());
        $mpdf->SetHTMLFooter(view('reports.layouts.pdf.footer')->render());

        // Start table
        $mpdf->WriteHTML('<table border="1" cellspacing="0" cellpadding="5" width="100%">
        <thead>
            <tr>
                <th>ID</th><th>First Name</th><th>Last Name</th>
                <th>Email</th><th>Mobile</th><th>Created At</th>
            </tr>
        </thead><tbody>');

        $chunk_count = 0;
        Guest::chunk(500, function ($guests) use ($mpdf, &$chunk_count) {
            Log::info('Processing chunk number: ' . (++$chunk_count));
            $chunkHtml = '';
            foreach ($guests as $guest) {
                $chunkHtml .= '<tr>
                <td>' . $guest->id . '</td>
                <td>' . e($guest->first_name) . '</td>
                <td>' . e($guest->last_name) . '</td>
                <td>' . e($guest->email) . '</td>
                <td>' . e($guest->mobile) . '</td>
                <td>' . $guest->created_at->format('Y-m-d') . '</td>
            </tr>';
            }
            $mpdf->WriteHTML($chunkHtml);
            //$mpdf->flush(); 
        });

        $mpdf->WriteHTML('</tbody></table>');

        // Stream PDF instead of holding in memory
        $mpdf->Output("LargePDF.pdf", \Mpdf\Output\Destination::INLINE);
    }

    public function handleLargePdf_v2()
    {
        // Initialize mPDF only once
        $this->mpdfService->mpdf("Large PDF");
        $mpdf = $this->mpdfService->getMpdf();
        $mpdf->SetHTMLHeader(view('reports.layouts.pdf.header')->render());
        $mpdf->SetHTMLFooter(view('reports.layouts.pdf.footer')->render());

        // Write the table opening tags once
        $mpdf->WriteHTML('<table border="1" cellspacing="0" cellpadding="5" width="100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>Created At</th>
            </tr>
        </thead><tbody>');

        // Use chunking to write rows directly to mPDF, reducing memory footprint
        $chunk_count = 0;
        Guest::chunk(500, function ($guests) use ($mpdf, &$chunk_count) {
            Log::info('Processing chunk number: ' . (++$chunk_count));
            // Prepare HTML for the current chunk
            $chunkHtml = '';
            foreach ($guests as $guest) {
                $chunkHtml .= '<tr>
                <td>' . $guest->id . '</td>
                <td>' . $guest->first_name . '</td>
                <td>' . $guest->last_name . '</td>
                <td>' . $guest->email . '</td>
                <td>' . $guest->mobile . '</td>
                <td>' . Carbon::parse($guest->created_at)->format('Y-m-d') . '</td>
            </tr>';
            }
            // Write the HTML chunk to the PDF
            $mpdf->WriteHTML($chunkHtml);
        });

        // Write the table closing tags once
        $mpdf->WriteHTML('</tbody></table>');

        // Output the final PDF
        $mpdf->Output("LargePDF.pdf", 'I');
    }
    public function handleLargePdf_v1(){
        $this->mpdfService->mpdf("Large PDF");
        $header = view('reports.layouts.pdf.header')->render();
        $footer = view('reports.layouts.pdf.footer')->render();
        //$this->mpdfService->mpdf($name);
        $mpdf = $this->mpdfService->getMpdf();
        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);
        $html = '<table border="1" cellspacing="0" cellpadding="5" width="100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Created At</th>
                    </tr>
                </thead><tbody>';
                Guest::chunk(1000, function($guests) use(&$html){
                    foreach($guests as $guest){
                        $html.='<tr>
                            <td>'.$guest->id.'</td>
                            <td>'.$guest->first_name.'</td>
                            <td>'.$guest->last_name.'</td>
                            <td>'.$guest->email.'</td>
                            <td>'.$guest->mobile.'</td>
                            <td>'.Carbon::parse($guest->created_at)->format('Y-m-d').'</td>
                        </tr>';
                    }
                });
        $html.='</tbody></table>';
        $mpdf->WriteHTML($html);
        $mpdf->Output("LargePDF.pdf", 'I');
    }

    public function sendReservationConfirmationMail(Request $request)
    {
        try {
            $name = "Reservation Confirmation";
            $validated = $request->validate([
                'sale_id' => 'required|integer',
                'email' => 'required|email',
                'subject' => 'required|string',
                'message' => 'required|string',
            ]);
            $sale_query = SaleRoom::find($validated['sale_id']);
           // dd($sale_query);
           if($sale_query && !empty($sale_query->token)){
                $data = $this->reportService->getReservationDetail($sale_query->token);
                if ($data) {
                    //dd($data);
                    $html = view('reports.reservation.pdf')->with(['data' => $data, 'title' => $name])->render();
                    $header = view('reports.layouts.pdf.header')->render();
                    $footer = view('reports.layouts.pdf.footer')->render();
                    $this->mpdfService->mpdf($name);
                    $mpdf = $this->mpdfService->getMpdf();
                    $mpdf->SetHTMLHeader($header);
                    $mpdf->SetHTMLFooter($footer);
                    $mpdf->WriteHTML($html);
                    $pdfContent = $mpdf->Output("{$data['saleRoom']->code}.pdf", 'S');
                    if (isset($data['saleRoom']) && !is_null($data['saleRoom'])) {
                        $customer_name = "{$data['saleRoom']['guest_name_title']} {$data['saleRoom']['guest_name_first']} {$data['saleRoom']['guest_name_last']}";
                        $customer_email = $data['saleRoom']['guest_email'];
                        $mobile = $data['saleRoom']['guest_mobile'];
                        $mailDetails = [
                            'sender_name' => config('ahits.property.name'),
                            'sender_mail' => config('ahits.property.email'),
                            'sender_mobile' => config('ahits.property.mobile'),
                            'sender_address' => config('ahits.property.location'),
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'subject' =>  $validated['subject'],
                            'message' => $validated['message'],
                            'pdfContent'=> $pdfContent,
                            'pdfFileName' => "{$data['saleRoom']->code}.pdf",
                            
                        ];
                        return $this->emailService->sendReservationMail($mailDetails);
                    } else {
                        return 0;
                    }
                    
                        }
                        }
                     } catch (\Exception $ex) {
                        return $this->withErrors($ex->getMessage());
                    }
                   

        return response()->json(['success' => true]);
    }
    public function sendLedgerMail(Request $request)
    {
        try {
            
            $validated = $request->validate([
                'sale_id' => 'required|integer',
                'emailType'=>'required|string',
                'mrId'=>'nullable|numeric',
                'email' => 'required|email',
                'subject' => 'required|string',
                'message' => 'required|string',
            ]);
            $sale_query = SaleRoom::find($validated['sale_id']);
            //dd($validated,$sale_query);

           if($sale_query && !empty($sale_query->token)){
                if($validated['emailType']==="money_receipt"){
                    $data = $this->reportService->getMoneyReceiptData($validated['mrId']);
                    $name = "Money Receipt - {$data->saleRoom->code}";
                    if ($data) {
                        $html = view('reports.sales.money-receipt.pdf')->with(['data' => $data, 'title' => $name])->render();
                    }
                } elseif($validated['emailType']==="ledger"){
                        $data = $this->reportService->getledgerData($sale_query->token);
                        $name = "Ledger Report " . $data['saleRoom']->code ?? "";
                        if ($data) {
                            //dd($data);
                            $html = view('reports.ledger.pdf')->with(['data' => $data, 'title' => $name])->render();
                        }

                }                
                    $header = view('reports.layouts.pdf.header')->render();
                    $footer = view('reports.layouts.pdf.footer')->render();
                    $this->mpdfService->mpdf($name);
                    $mpdf = $this->mpdfService->getMpdf();
                    $mpdf->SetHTMLHeader($header);
                    $mpdf->SetHTMLFooter($footer);
                    $mpdf->WriteHTML($html);
                    $pdfContent = $mpdf->Output("{$data['saleRoom']->code}.pdf", 'S');
                    if (isset($data['saleRoom']) && !is_null($data['saleRoom'])) {
                        $customer_name = "{$data['saleRoom']['guest_name_title']} {$data['saleRoom']['guest_name_first']} {$data['saleRoom']['guest_name_last']}";
                        $customer_email = $data['saleRoom']['guest_email'];
                        $mobile = $data['saleRoom']['guest_mobile'];
                        $mailDetails = [
                            'sender_name' => config('ahits.property.name'),
                            'sender_mail' => config('ahits.property.email'),
                            'sender_mobile' => config('ahits.property.mobile'),
                            'sender_address' => config('ahits.property.location'),
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'subject' =>  $validated['subject'],
                            'message' => $validated['message'],
                            'pdfContent'=> $pdfContent,
                            'pdfFileName' => "{$data['saleRoom']->code}.pdf",
                            
                        ];
                        return $this->emailService->sendReservationMail($mailDetails);
                    } else {
                        return 0;
                    }
                        }
                     } catch (\Exception $ex) {
                        return $this->withErrors($ex->getMessage());
                    }
                   

        return response()->json(['success' => true]);
    }
}
