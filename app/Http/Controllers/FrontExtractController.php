<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FrontExtractController extends Controller
{
    /**
     * Data extraction from the front-end.
     */
    public function extracting()
    {
        return view('extracting.front');
    }

    /**
     * Testing the front-end.
     */
    public function test()
    {
        return view('extracting.test');
    }
}
