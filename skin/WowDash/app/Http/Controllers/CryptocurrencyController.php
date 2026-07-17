<?php

namespace App\Http\Controllers;

class CryptocurrencyController extends Controller
{
    public function wallet()
    {
        return view('cryptocurrency/wallet');
    }
}
