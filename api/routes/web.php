<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Pages légales publiques
|--------------------------------------------------------------------------
|
| L'URL /politique-de-confidentialite est celle déclarée dans la Google Play
| Console (champ « URL des règles de confidentialité »). Les autres chemins
| sont des alias conservés pour les liens déjà diffusés.
|
*/

Route::get('/politique-de-confidentialite', function () {
    return view('legal.privacy');
})->name('legal.privacy');

Route::redirect('/privacy', '/politique-de-confidentialite');
Route::redirect('/confidentialite', '/politique-de-confidentialite');
