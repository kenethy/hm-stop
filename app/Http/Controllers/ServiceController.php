<?php

namespace App\Http\Controllers;

class ServiceController extends Controller
{
    public function index()
    {
        return view('pages.services', [
            'title' => 'Layanan'
        ]);
    }
}
