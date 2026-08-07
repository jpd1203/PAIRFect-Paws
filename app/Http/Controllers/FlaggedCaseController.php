<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FlaggedCaseController extends Controller
{
    /**
     * GET /flagged-cases/overdue-notice
     */
    public function overdueNotice()
    {
        return view('flagged-cases.overdue-notice');
    }

    /**
     * GET /flagged-cases/flagged-notice
     */
    public function flaggedNotice()
    {
        return view('flagged-cases.flagged-notice');
    }
}
