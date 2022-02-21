<?php

namespace App\Http\Controllers\Auth;

use Session;
use App\Model\EmployeeMaster;
use App\Model\EmployeeEAccess;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller {
    /*
      |--------------------------------------------------------------------------
      | Login Controller
      |--------------------------------------------------------------------------
      |
      | This controller handles authenticating users for the application and
      | redirecting them to your home screen. The controller uses a trait
      | to conveniently provide its functionality to your applications.
      |
     */

use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest')->except('logout');
    }

    public function login(Request $request) {
        $input = request()->all();
        $credentials = [
            'EID' => $input['EID'],
            'password' => $input['password']
        ];
        if (auth()->attempt($credentials)) {
            $auth = auth()->user();
            $user = EmployeeMaster::where(['EID' => $auth->EID])->first();
            if (!$user) {
                auth()->logout();
                Session::put('error', 'Invalid Details');
                return redirect()->back();
            }
            if ($user->EStatus == EmployeeMaster::EStatusLeft) {
                auth()->logout();
                Session::put('error', 'Employee Left.');
                return redirect()->back();
            }
            if ($user->accountStatus == EmployeeMaster::AccountStatusDiactivated) {
                auth()->logout();
                Session::put('error', 'Need account approval.');
                return redirect()->back();
            }
            $allowedUser = [EmployeeEAccess::ACCESS_RM, EmployeeEAccess::ACCESS_STL, EmployeeEAccess::ACCESS_OM, EmployeeEAccess::ACCESS_CH, EmployeeEAccess::ACCESS_PM, EmployeeEAccess::ACCESS_BO, EmployeeEAccess::ACCESS_ADMIN, EmployeeEAccess::ACCESS_SUPER_ADMIN, EmployeeEAccess::ACCESS_TM];
            if (!in_array($user->EAccess, $allowedUser)) {
                auth()->logout();
                Session::put('error', 'Unauthenticated.');
                return redirect()->back();
            }
            $this->user = $user;
            Session::put('info', 'Login Success');
            if (Session::get('referTo')) {
                $redirectUrl = Session::get('referTo');
                Session::remove('referTo');
                return redirect($redirectUrl);
            }
            return redirect()->route('home');
        }
        return redirect()->back()->with('error', 'Invalid Details');
    }

}
