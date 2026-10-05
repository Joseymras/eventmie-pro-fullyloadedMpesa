<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Models\Event;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('/registers', [UserController::class, 'register']);

Route::post('/login', [UserController::class, 'login']);


Route::post('/checkout', function(Request $request){
        

    return response()->json(['status' => true, 'data' => json_decode($request->data) ]);
});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('get/events', function(){
        $events = Event::with('tickets')->paginate(5);

        return response()->json(['status' => true, 'events' => $events]);
    });

    
    
    Route::get('user', function (Request $request) {
        return json_decode($request->user());
    });

    // Route::post('/checkout', function(Request $request){
       
    //     return response()->json(['status' => true, 'data' => $request->all()]);
    // });
    
});
