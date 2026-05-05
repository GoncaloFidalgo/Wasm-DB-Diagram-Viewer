<?php

use App\Filament\Pages\DiagramViewer;
use Illuminate\Support\Facades\Route;

Route::get('/diagram/{id}', DiagramViewer::class)->name('diagram.view');
