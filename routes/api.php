<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventPublicController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\SeatMapController;
use App\Http\Controllers\VenueMapTemplateController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\VenueMapVersionController;
use App\Http\Controllers\UserAccountController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\TicketPdfController;
use App\Http\Controllers\PublicSeatController;
use App\Http\Controllers\ProducerController;
use App\Http\Controllers\ProducerFinancialController;
use App\Http\Controllers\PlaceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::prefix('auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [LoginController::class, 'me']);

    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/{id}', [EventController::class, 'show']);
        Route::post('/', [EventController::class, 'create']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::patch('/{id}', [EventController::class, 'update']);
        Route::post('/{id}/banner', [EventController::class, 'uploadBanner']);
    });

    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{id}', [CartController::class, 'update']);
        Route::delete('/{id}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::post('/merge', [CartController::class, 'merge']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
    });

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/checkout/preview', [CheckoutController::class, 'preview']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/tickets/pdf', [TicketPdfController::class, 'orderPdf']);

    Route::get('/user/dashboard', [UserAccountController::class, 'dashboard']);

    Route::get('/my-tickets', [TicketController::class, 'myTickets']);
    Route::get('/events/{eventId}/tickets', [TicketController::class, 'eventTickets']);

    Route::get('/sectors/{sectorId}/seats', [SeatController::class, 'index']);
    Route::get('/events/{eventId}/seat-map', [SeatMapController::class, 'show']);
    Route::put('/events/{eventId}/seat-map', [SeatMapController::class, 'update']);
    Route::post('/events/{eventId}/seat-map/floor-plan', [SeatMapController::class, 'uploadFloorPlan']);
    Route::post('/events/{eventId}/seat-map/generate-row', [SeatMapController::class, 'generateRow']);
    Route::post('/events/{eventId}/seat-map/seats', [SeatMapController::class, 'createSeats']);
    Route::delete('/events/{eventId}/seat-map/seats', [SeatMapController::class, 'deleteSeats']);
    Route::get('/events/{eventId}/seat-map/versions', [VenueMapVersionController::class, 'index']);
    Route::post('/events/{eventId}/seat-map/versions', [VenueMapVersionController::class, 'store']);
    Route::post('/events/{eventId}/seat-map/versions/{versionId}/restore', [VenueMapVersionController::class, 'restore']);

    Route::get('/venues', [VenueController::class, 'index']);
    Route::post('/venues', [VenueController::class, 'store']);
    Route::post('/venues/{venueId}/maps/from-event', [VenueController::class, 'saveMapFromEvent']);
    Route::post('/venues/{venueId}/apply-to-event', [VenueController::class, 'applyToEvent']);

    Route::get('/places', [PlaceController::class, 'index']);
    Route::get('/places/{id}', [PlaceController::class, 'show']);
    Route::post('/places', [PlaceController::class, 'store']);
    Route::put('/places/{id}', [PlaceController::class, 'update']);
    Route::patch('/places/{id}', [PlaceController::class, 'update']);
    Route::delete('/places/{id}', [PlaceController::class, 'destroy']);

    Route::get('/map-templates', [VenueMapTemplateController::class, 'index']);
    Route::get('/map-templates/{id}', [VenueMapTemplateController::class, 'show']);
    Route::post('/map-templates', [VenueMapTemplateController::class, 'store']);
    Route::delete('/map-templates/{id}', [VenueMapTemplateController::class, 'destroy']);

    // ─── Produtor: perfil e configurações financeiras ───────────────────
    Route::prefix('producer')->group(function () {
        Route::get('/', [ProducerController::class, 'show']);
        Route::post('/complete-financial-profile', [ProducerController::class, 'completeFinancialProfile']);
        Route::patch('/payment-settings', [ProducerController::class, 'updatePaymentSettings']);

        // Dashboard financeiro e pedidos
        Route::get('/financial/dashboard', [ProducerFinancialController::class, 'dashboard']);
        Route::get('/financial/orders', [ProducerFinancialController::class, 'orders']);
        Route::post('/financial/orders/{orderId}/refund', [ProducerFinancialController::class, 'refundOrder']);
        Route::post('/financial/calculate', [ProducerFinancialController::class, 'calculate']);
    });

});

Route::post('/seats/hold', [SeatMapController::class, 'hold']);
Route::post('/seats/release-holds', [SeatMapController::class, 'releaseHolds']);

Route::middleware('auth.optional')->prefix('cart')->group(function () {
    Route::get('/{cartId}', [CartController::class, 'showByUuid'])
        ->whereUuid('cartId');
    Route::post('/items', [CartController::class, 'storeItem']);
    Route::put('/items/{id}', [CartController::class, 'updateItem']);
    Route::delete('/items/{id}', [CartController::class, 'destroyItem']);
});

Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle']);

Route::post('/tickets/validate', [TicketController::class, 'validate']);

Route::prefix('public')->group(function () {
    Route::get('/events', [EventPublicController::class, 'index']);
    Route::get('/events/{slug}', [EventPublicController::class, 'getBySlug']);
    Route::get('/events/{slug}/seat-map', [SeatMapController::class, 'publicShow']);
    Route::get('/sectors/{sectorId}/seats', [PublicSeatController::class, 'index']);
    Route::get('/tickets/{hash}', [PublicTicketController::class, 'show']);
    Route::get('/tickets/{hash}/pdf', [PublicTicketController::class, 'pdf']);
});
