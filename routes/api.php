<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/time-entries/meta-data', [TimeEntryController::class, 'getMetaData']);
Route::get('/time-entries', [TimeEntryController::class, 'index']);
Route::post('/time-entries', [TimeEntryController::class, 'store']);
Route::put('/time-entries/{timeEntry}', [TimeEntryController::class, 'update']);
