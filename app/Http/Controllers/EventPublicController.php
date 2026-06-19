<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Http\Resources\EventResource;

class EventPublicController extends Controller
{
   public function index()
   {
    return EventResource::collection(Event::all());
   }

   public function getBySlug(string $slug)
   {
    $event = Event::where('slug', $slug)->first();
    if (!$event) {
        return response()->json(['message' => 'Event not found'], 404);
    }
    return new EventResource($event);
   }
   
}   