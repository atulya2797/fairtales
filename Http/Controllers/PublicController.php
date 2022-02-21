<?php

namespace App\Http\Controllers;

class PublicController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // space that we can use the repository from
    public function __construct() {
        parent::__construct();
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function privacyPolicy() {
        return view('public.privacyPolicy');
    }

}
