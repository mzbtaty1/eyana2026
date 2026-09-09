<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;

class LoginController extends Controller
{
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
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function do_login(Request $request){
        
     $st = 200;
        if(!isset($request->email) or !isset($request->password)){
            $st = 901;
        }
         if (Auth::attempt($request->only(["email", "password"]) , $remember = true)) {
              
             $user = User::select('*')->where('email' , $request->email)->get();
             if(count($user) == 1){
                 $user = $user[0];
             if($user->status == 0){
                 Auth::logout();
                  
                 $st = 403;
             }else{
                 $st = 200;
             }
             }else{
                 $st = 404;
             }
             

              

            } else {

                $st = 404;

            }
        
        
        
        
        return response()->json([
    'status_code' => $st,
   
]);
        
    }
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }
}
