<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/extract');

Route::livewire('/extract', 'pages::database.extract')->name('extract');
Route::livewire('/diagram/{id}', 'pages::diagram');
