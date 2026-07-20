<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BackendExtractController extends Controller
{
    /**
     * Data extraction from the front-end.
     */
    public function extracting()
    {
        return view('extracting.backend');
    }
}
