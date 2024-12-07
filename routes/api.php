<?php

use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

















Route::get('/report', [ReportController::class, 'generateReport']);
