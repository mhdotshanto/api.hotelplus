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

//test routs
Route::get('test-api', function () {
    return response()->json('working.....');
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    //Misc api call
    Route::prefix('misc')->controller(\App\Http\Controllers\api\MiscController::class)->group(function () {
        Route::get('/insert-room-select-options', 'getInsertRoomSelectOption');
        Route::get('/category-options', 'getCategoryOptions');
        Route::get('/room-name-options', 'getRoomNameOptions');
        Route::get('/floor-options', 'getFloorOptions');
        Route::get('/country-options', 'getCountryOptions');
        Route::get('/reference-options', 'getReferenceOptions');
        Route::get('/preference-options', 'getPreferenceOptions');
    });

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

    Route::post('room-temp-block', [SaleRoomController::class, 'roomTempBlock']);
    Route::delete('room-temp-details/{roomTempDetail}', [SaleRoomController::class, 'destroyRoomTempDetail']);

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

    Route::resource('config', ConfigController::class)->only(['index', 'update']);

    Route::post('sale-room', [SaleRoomController::class, 'saleRoom']);
    Route::post('update-sale-room/{saleId}', [SaleRoomController::class, 'updateSaleRoom']);

    Route::get('sale-by-code', [SaleRoomController::class, 'saleRoomRecordByCode']);
    Route::get('sale-record-for-success-message', [SaleRoomController::class, 'getSaleRecordForSuccessMessage']);
    Route::get('sale-by-id/{saleRoom}', [SaleRoomController::class, 'saleRoomRecordById']);

    Route::post('checkin/{saleRoom}', [SaleRoomController::class, 'updateToCheckIn']);
});

Route::get('print-block-detail/{id}', [RoomBlockController::class, 'printBlockDetail']);




Route::get('print-reservation-detail/{id}', [SaleRoomController::class, 'printReservationDetail']);
