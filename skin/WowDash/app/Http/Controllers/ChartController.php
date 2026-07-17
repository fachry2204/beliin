<?php

namespace App\Http\Controllers;

class ChartController extends Controller
{
    public function columnChart()
    {
        return view('chart/columnChart');
    }

    public function lineChart()
    {
        return view('chart/lineChart');
    }

    public function pieChart()
    {
        return view('chart/pieChart');
    }
}
