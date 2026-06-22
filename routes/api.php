<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
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
use App\Http\Controllers\PublicSeatController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
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
    });

    Route::post('/checkout', [CheckoutController::class, 'store']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);

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

    Route::get('/map-templates', [VenueMapTemplateController::class, 'index']);
    Route::get('/map-templates/{id}', [VenueMapTemplateController::class, 'show']);
    Route::post('/map-templates', [VenueMapTemplateController::class, 'store']);
    Route::delete('/map-templates/{id}', [VenueMapTemplateController::class, 'destroy']);
});

Route::post('/seats/hold', [SeatMapController::class, 'hold']);
Route::post('/seats/release-holds', [SeatMapController::class, 'releaseHolds']);

Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle']);

Route::post('/tickets/validate', [TicketController::class, 'validate']);

Route::prefix('public')->group(function () {
    Route::get('/events', [EventPublicController::class, 'index']);
    Route::get('/events/{slug}', [EventPublicController::class, 'getBySlug']);
    Route::get('/events/{slug}/seat-map', [SeatMapController::class, 'publicShow']);
    Route::get('/sectors/{sectorId}/seats', [PublicSeatController::class, 'index']);
});
