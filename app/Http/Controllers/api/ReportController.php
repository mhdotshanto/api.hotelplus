<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RoomName;
use App\Models\RoomNumber;
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
use mysql_xdevapi\Exception;

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
        try {
            $validated = $request->validate([
                'output' => 'required|string',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'references' => 'nullable|array',
                'references.*' => 'string',
            ]);

            $saleRoomQuery = SaleRoom::with([
                    'sale_agent' => function ($saleAgentQry) {
                        $saleAgentQry->select('id', 'name', 'user_name');
                    },
                ])
                ->withSum('saleLedgers as total_debit', 'debit')
                ->withSum('saleLedgers as total_credit', 'credit')
                ->where('status', 'checkedout')
                ->whereBetween('created_at', [
                    Carbon::parse($validated['date_from'])->startOfDay(),
                    Carbon::parse($validated['date_to'])->endOfDay(),
                ])
                ->select(
                    'id',
                    'code',
                    'date_checkin',
                    'date_checkout',
                    'guest_name_title',
                    'guest_name_first',
                    'guest_name_last',
                    'guest_mobile',
                    'ref_id',
                    'room_qty',
                    'price_gross',
                    'price_net',
                    'tip_amount',
                    'date',
                );

            if (!empty($validated['references'])) $saleRoomQuery->whereIn('ref_id', $validated['references']);

            $saleRoomData = $saleRoomQuery->get();

            $totals = [
                'room_qty' => $saleRoomData->sum('room_qty'),
                'gross_price' => $saleRoomData->sum('price_gross'),
                'net_price' => $saleRoomData->sum('price_net'),
                'due' => $saleRoomData->sum('total_due'),
                'tip' => $saleRoomData->sum('tip_amount'),
            ];

            $data = [
                'title' => "Checkout Report",
                'from_date' => $validated['date_from'],
                'to_date' => $validated['date_to'],
                'saleRooms' => $saleRoomData,
                'sale_agents' => isset($validated['references']) ? $saleRoomData->pluck('sale_agent.name')->unique()->values() : null,
                'totals' => $totals,
                'routes' => [
                    'pdf' => route(
                        'checkedout',[
                            'output' => 'pdf',
                            'date_from' => $validated['date_from'],
                            'date_to' => $validated['date_to'],
                            'references' => $validated['references'] ?? null,
                        ]
                    ),
                    'excel' => route(
                        'checkedout',
                        [
                            'output' => 'excel',
                            'date_from' => $validated['date_from'],
                            'date_to' => $validated['date_to'],
                            'references' => $validated['references'] ?? null,
                        ]
                    )
                ],
            ];

//            return response()->json($data);

            if ($validated['output'] === 'html') return view('reports.sales.checkout.html')->with($data);
            if ($validated['output'] === "excel") return Excel::download(new CheckoutExport($data, $data['title']),"{$data['title']}.xlsx");
            if($validated['output'] === "pdf") {
                $html = view('reports.sales.checkout.pdf')->with(['data' => $data])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($data['title']);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$data['title']}.pdf", 'I');
            }
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Something Went Wrong!',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function checkinReport(Request $request)
    {
        try {
            $validated = $request->validate([
                'output' => 'required|string',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'references' => 'nullable|array',
                'references.*' => 'string',
            ]);

            $saleRoomQuery = SaleRoom::with([
                    'sale_agent' => function ($saleAgentQry) {
                        $saleAgentQry->select('id', 'name', 'user_name');
                    },
                ])
                ->withSum('saleLedgers as total_debit', 'debit')
                ->withSum('saleLedgers as total_credit', 'credit')
                ->where('status', 'checkedin')
                ->whereBetween('created_at', [
                    Carbon::parse($validated['date_from'])->startOfDay(),
                    Carbon::parse($validated['date_to'])->endOfDay(),
                ])
                ->select(
                    'id',
                    'code',
                    'date_checkin',
                    'date_checkout',
                    'guest_name_title',
                    'guest_name_first',
                    'guest_name_last',
                    'guest_mobile',
                    'ref_id',
                    'room_qty',
                    'price_gross',
                    'price_net',
                    'tip_amount',
                    'date',
                );

            if (!empty($validated['references'])) $saleRoomQuery->whereIn('ref_id', $validated['references']);

            $saleRoomData = $saleRoomQuery->get();

            $totals = [
                'room_qty' => $saleRoomData->sum('room_qty'),
                'gross_price' => $saleRoomData->sum('price_gross'),
                'net_price' => $saleRoomData->sum('price_net'),
                'due' => $saleRoomData->sum('total_due'),
                'tip' => $saleRoomData->sum('tip_amount'),
            ];

            $data = [
                'title' => "Checkin Report",
                'from_date' => $validated['date_from'],
                'to_date' => $validated['date_to'],
                'saleRooms' => $saleRoomData,
                'sale_agents' => isset($validated['references']) ? $saleRoomData->pluck('sale_agent.name')->unique()->values() : null,
                'totals' => $totals,
                'routes' => [
                    'pdf' => route(
                        'checkedin',[
                            'output' => 'pdf',
                            'date_from' => $validated['date_from'],
                            'date_to' => $validated['date_to'],
                            'references' => $validated['references'] ?? null,
                        ]
                    ),
                    'excel' => route(
                        'checkedin',
                        [
                            'output' => 'excel',
                            'date_from' => $validated['date_from'],
                            'date_to' => $validated['date_to'],
                            'references' => $validated['references'] ?? null,
                        ]
                    )
                ],
            ];

            if ($validated['output'] === 'html') return view('reports.sales.checkin.html')->with($data);
            if ($validated['output'] === "excel") return Excel::download( new CheckinExport($data, $data['title']), "{$data['title']}.xlsx");
            if($validated['output'] == "pdf") {
                $html = view('reports.sales.checkin.pdf')->with(['data' => $data])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($data['title']);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$data['title']}.pdf", 'I');
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
        try {
            $validated = $request->validate([
                'output' => 'required|string',
                'date' => 'required|date',
                'roomType'=>'nullable|string',
            ]);

            $validated['roomType'] = $validated['roomType'] ?? "all";

            $roomData = RoomNumber::with([
                    'roomName' => function ($roomNameQry) {
                        $roomNameQry->select('id', 'category_id')
                            ->with('category', function ($categoryQry) {
                                $categoryQry->select('id', 'name');
                            });
                    },
                    'room_sale_allocation' => function ($roomSaleAllocationQry) use($validated) {
                        $roomSaleAllocationQry
                            ->whereDate('date', $validated['date'])
                            ->with('saleRoom', function ($saleRoomQry) {
                                $saleRoomQry->with([
                                        'sale_agent' => function ($saleAgentQry) {
                                            $saleAgentQry->select('id', 'name', 'user_name');
                                        },
                                        'sale_user' => function ($saleUserQry) {
                                            $saleUserQry->select('id', 'name', 'user_name');
                                        },
                                        'sale_tip'
                                    ])
                                    ->select(
                                        'id',
                                        'code',
                                        'guest_name_title',
                                        'guest_name_first',
                                        'guest_name_last',
                                        'guest_mobile',
                                        'guest_address',
                                        'guest_qty',
                                        'ref_id',
                                        'user_id',
                                        'date_checkin',
                                        'date_checkout',
                                        'status'
                                    );
                            })
                            ->select(
                                'id',
                                'room_sale_id',
                                'room_number_id',
                                'rate_sale',
                                'tip_amount',
                                'rate_net'
                            );
                    }
                ])
                ->select(
                    'id',
                    'name',
                    'room_name_id'
                )
                ->get();
            $allocations = $roomData->pluck('room_sale_allocation')->flatten();
            $summary = collect([
                'total_not_sold_room' => fn () => $roomData->where(
                    fn ($r) => $r->room_sale_allocation->isEmpty()
                )->count(),

                'total_sold_room' => fn () => $roomData->where(
                    fn ($r) => $r->room_sale_allocation->isNotEmpty()
                )->count(),

                'total_sale' => fn () => $allocations->sum('rate_sale'),
                'total_commision' => fn () => $allocations->sum('tip_amount'),
                'total_net_sale' => fn () => $allocations->sum('rate_net'),
                'total_income' => fn () => $allocations->sum('rate_net'),
            ])->map(fn ($fn) => $fn());
            $data = [
                'title' => "Daily Room Report",
                'rooms' => $roomData,
                'date' => $validated['date'],
                'routes' => [
                    'pdf' => route(
                        'daily-room-report',[
                            'output' => 'pdf',
                            'date' => $validated['date'],
                            'roomType' => $validated['roomType'],
                        ]
                    ),
                    'excel' => route(
                        'daily-room-report',
                        [
                            'output' => 'excel',
                            'date' => $validated['date'],
                            'roomType' => $validated['roomType'],
                        ]
                    )
                ],
                'summary' => $summary
            ];
            if($validated['output'] === 'html') return view('reports.sales.daily-room-report.html')->with($data);
            if($validated['output'] === 'excel') return Excel::download(new DailyRoomExport($data, $data['title']),"{$data['title']}.xlsx");
            if($validated['output'] === 'pdf') {
                $html = view('reports.sales.daily-room-report.pdf')->with(['data' => $data, 'title' => $data['title']])->render();
                $header = view('reports.layouts.pdf.header')->render();
                $footer = view('reports.layouts.pdf.footer')->render();
                $this->mpdfService->mpdf($data['title']);
                $mpdf = $this->mpdfService->getMpdf();
                $mpdf->SetHTMLHeader($header);
                $mpdf->SetHTMLFooter($footer);
                $mpdf->WriteHTML($html);
                $mpdf->Output("{$data['title']}.pdf", 'I');
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
                //dd("test",$data);
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
            return $mpdf->Output("{$data->saleRoom->code}_Money_Receipt.pdf", 'I');
            // return response($mpdf->Output("money-receipt-{$id}.pdf", "S"))
            //     ->header('Content-Type', 'application/pdf')
            //     ->header('Content-Disposition', 'attachment; filename="money-receipt-' . $id . '.pdf"')
            //     ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }
    }
    public function printMoneyReceiptAuthenticated($mrNo)
    {
        $data = $this->reportService->getMoneyReceiptData($mrNo);
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
            return $mpdf->Output("money-receipt-authenticated-{$mrNo}.pdf", "I");
//            return response()->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="money-receipt-authenticated' . $mrNo . '.pdf"')->header('Cache-Control', 'no-store, no-cache, must-revalidate');
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
                'mrNo'=>'nullable|string',
                'email' => 'required|email',
                'subject' => 'required|string',
                'message' => 'required|string',
            ]);
            $sale_query = SaleRoom::find($validated['sale_id']);
            //dd($validated,$sale_query);

           if($sale_query && !empty($sale_query->token)){
                if($validated['emailType']==="money_receipt"){
                    $data = $this->reportService->getMoneyReceiptData($validated['mrNo']);
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
