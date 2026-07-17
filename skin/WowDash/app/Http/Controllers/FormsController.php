<?php

namespace App\Http\Controllers;

class FormsController extends Controller
{
    public function form()
    {
        return view('forms/form');
    }

    public function formLayout()
    {
        return view('forms/formLayout');
    }

    public function formValidation()
    {
        return view('forms/formValidation');
    }

    public function wizard()
    {
        return view('forms/wizard');
    }
}
