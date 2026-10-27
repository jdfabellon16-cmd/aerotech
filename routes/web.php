<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');
Route::view('/app/{path?}', 'app')->where('path', '.*');
