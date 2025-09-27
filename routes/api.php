<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\RoomNameController;
use App\Http\Controllers\api\RoomCategoryController;
use App\Http\Controllers\api\RoomFloorController;
use App\Http\Controllers\api\RoomServiceController;
use App\Http\Controllers\api\RoomNumberController;
use App\Http\Controllers\api\RoomHouseKeepingController;
use App\Http\Controllers\api\RoomSpecialRateController;
use App\Http\Controllers\api\RoomTagController;
use App\Http\Controllers\api\RoomCategoryTagController;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\UserController;
use App\Http\Controllers\api\RoomBlockController;
use App\Http\Controllers\api\SaleRoomController;
use App\Http\Controllers\api\GuestController;
use App\Http\Controllers\api\ConfigController;
use App\Http\Controllers\api\ReportController;
use App\Http\Controllers\api\MiscController;

Route::get('test-api', function () {
    return response()->json('working.....');
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);
    Route::get('/user', [AuthController::class, 'me']);

    //Misc api call
    Route::prefix('misc')->controller(\App\Http\Controllers\api\MiscController::class)->group(function () {
        Route::get('/pre-open-application-event', 'preOpenApplicationEvent');
        Route::get('/insert-room-select-options', 'getInsertRoomSelectOption');
        Route::get('/category-options', 'getCategoryOptions');
        Route::get('/room-name-options', 'getRoomNameOptions');
        Route::get('/floor-options', 'getFloorOptions');
        Route::get('/country-options', 'getCountryOptions');
        Route::get('/reference-options', 'getReferenceOptions');
        Route::get('/preference-options', 'getPreferenceOptions');
        Route::get('/payment-platform-options', 'getPaymentPlatformOptions');
        Route::get('/party-options', 'getPartyOptions');
        Route::get('/global-search-options', 'getGlobalSearchOptions');
        Route::get('/get-status-counts', 'getStatusCounts');
    });

    Route::apiResource('user', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('update-users-password/{user}', [UserController::class, 'updateUsersPassword']);

    Route::get("/user-list", [UserController::class, 'index']);
    Route::get("/user-get/{id}", [UserController::class, 'getUser']);
    Route::put("/user-update/{user}", [UserController::class, 'update']);
    Route::post("/user-status-change/{user}", [UserController::class, 'changeStatus']);

    Route::post('create-preference', [\App\Http\Controllers\api\PreferenceController::class, 'create']);

    Route::resource('room-name', RoomNameController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('room-category', RoomCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('room-floor', RoomFloorController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('room-service', RoomServiceController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('room-number', RoomNumberController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post("/room-number/store-excel", [RoomNumberController::class, 'storeExcel']);
    Route::get("/room-number-all", [RoomNumberController::class, 'getAllRooms']);
    Route::post("/room-number/search", [RoomNumberController::class, 'searchBySpecialRate']);

    Route::resource('room-house-keeping', RoomHouseKeepingController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('room-special-rate', RoomSpecialRateController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('room-tag', RoomTagController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('room-category-tag', RoomCategoryTagController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('block-list/{user}', [RoomBlockController::class, 'index']);
    Route::get('block-detail/{id}', [RoomBlockController::class, 'blockDetail']);
    Route::post('block-room', [RoomBlockController::class, 'blockRooms']);
    Route::post('block-all-rooms', [RoomBlockController::class, 'blockAll']);

    Route::get('sale-create/{user}', [SaleRoomController::class, 'saleCreate']);
    Route::get('sales-info', [SaleRoomController::class, 'salesInfo']);
    Route::get('sales-info-exb', [SaleRoomController::class, 'salesInfoExb']);
    Route::get('search-reservation', [SaleRoomController::class, 'searchReservation']);
    Route::get('sales-room-allocations', [SaleRoomController::class, 'getRoomAllocations']);

    Route::post('sale-block', [SaleRoomController::class, 'saleBlock']);
    Route::post('sale-release', [SaleRoomController::class, 'saleRelease']);

    Route::get('tempted-rooms', [\App\Http\Controllers\api\RoomTempDetailController::class, 'getTemptedRoomList']);
    Route::post('toggle-room-xb/{roomTempDetail}', [SaleRoomController::class, 'toggleRoomXb']);

    Route::post('sales-add-exb', [SaleRoomController::class, 'addExtraBed']);
    Route::post('sales-release-exb', [SaleRoomController::class, 'releaseExtraBed']);
    Route::post('sales-store', [SaleRoomController::class, 'storeSale']);
    Route::post('guest-create', [GuestController::class, 'store']);
    Route::get('guests-search', [GuestController::class, 'search']);
    Route::get('guest-by-sale/{saleRoom}', [GuestController::class, 'guestBySale']);

    Route::controller(ConfigController::class)->group(function () {
        Route::get('config', 'index');
        Route::post('config-update/{config}', 'update');
        Route::get('application-configs', 'getApplicationConfigs');
    });

    Route::controller(SaleRoomController::class)->group(function () {
        Route::post('room-temp-block', 'roomTempBlock');
        Route::delete('room-temp-details/{roomTempDetail}', 'destroyRoomTempDetail');
        Route::post('sale-room', 'saleRoom');
        Route::post('update-sale-room/{saleId}', 'updateSaleRoom');
        Route::post('edit-sale/{saleRoom}', 'editSale');
        Route::get('sale-by-code', 'saleRoomRecordByCode');
        Route::get('sale-record-for-success-message', 'getSaleRecordForSuccessMessage');
        Route::get('sale-by-id/{saleRoom}', 'saleRoomRecordById');
        Route::get('sale-record-for-payment', 'getSaleRecordForPayment');
        Route::post('checkin/{saleRoom}', 'updateToCheckIn');
        Route::post('checkout/{saleRoom}', 'updateToCheckOut');
        Route::get('reservation-list', 'reservationList');
        Route::get('get-voucher-list', 'getVoucherList');
        Route::get('get-money-receipts', 'getMoneyReceipts');
        Route::get('get-ledger-list', 'getLedgerListBySaleId');
        Route::post('discard-changes/{sale_id}', 'discardChanges');
        
    });

    Route::controller(\App\Http\Controllers\api\RoomTempController::class)->group(function (){
        Route::get('current-users-temp-list', 'getCurrentUsersTempList');
        Route::post('extend/{roomTemp}', 'extend');
        Route::delete('destroy/{roomTemp}', 'destroy');
    });

    Route::resource('acc-banks', \App\Http\Controllers\api\AccBankController::class);
    Route::apiResource('acc-parties', \App\Http\Controllers\api\AccPartyController::class);

    Route::controller(\App\Http\Controllers\api\PaymentController::class)->group(function () {
        Route::post('make-a-payment', 'makeAPayment');
        Route::post('refund', 'refund');
        Route::post('tip', 'tip');
        Route::post('adjust-or-discount-sale/{saleRoom}', 'adjustOrDiscountSale');
        Route::get('payments-summery/{saleId}', 'getPaymentSummary');
        Route::post('full-sale-cancellation/{saleRoom}', 'fullSaleCancellation');
        Route::post('refund-and-cancel-reservation/{saleRoom}', 'refundAndCancelReservation');
    });
    Route::get('money-receipt-authenticated/{mrNo}', [ReportController::class, 'printMoneyReceiptAuthenticated'])->name('money-receipt-authenticated');
    Route::post('reservation/send-mail', [ReportController::class, 'sendReservationConfirmationMail'])->name('send.reservation.confirmation.mail');
    Route::post('ledger/send-mail', [ReportController::class, 'sendLedgerMail'])->name('send.ledger.mail');
});
Route::get('print-block-detail/{id}', [RoomBlockController::class, 'printBlockDetail']);
Route::get('reservation', [ReportController::class, 'printReservationDetail'])->name('reservation');
Route::get('ledger', [ReportController::class, 'ledgerReport'])->name('ledger');
Route::get('checkedin', [ReportController::class, 'checkinReport'])->name('checkedin');
Route::get('checkedout', [ReportController::class, 'checkoutReport'])->name('checkedout');
Route::get('daily-room', [ReportController::class, 'dailyRoomReport'])->name('daily-room-report');
Route::get('inhouse-guest', [ReportController::class, 'inhouseGuestReport'])->name('inhouse-guest-report');
Route::get('arrival-guest', [ReportController::class, 'arrivalGuestReport'])->name('arrival-guest-report');
Route::get('all-guests', [ReportController::class, 'allGuestReport'])->name('all-guest-report');
Route::get('guest-report-by-date', [ReportController::class, 'guestReportByDate'])->name('guest-report-by-date');
Route::get('money-receipt/{id}', [ReportController::class, 'printMoneyReceipt'])->name('money-receipt');



