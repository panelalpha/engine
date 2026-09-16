<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServerMetricsController;

Route::get('/metrics', [ServerMetricsController::class, 'webGraphs']);
