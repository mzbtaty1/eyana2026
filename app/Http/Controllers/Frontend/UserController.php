<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use App\Models\Password;
class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('my_account.view');
    }
public function password ()
    {
    $Passwords = Password::select('*')->orderBy('id','DESC')->get();
    
        return view('passwords.all' ,['passwords' => $Passwords]);
    }
public function password_create  ()
    {
    
        return view('passwords.create');
    }
public function password_save  (Request $request)
    {
    
        $create = Password::create([
            "url" => $request->url,
            "user" => $request->user,
            "pass" => $request->pass,
        ]);
    return redirect()->route('site.password');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function admins()
    {
        $admins = User::select('*')->get();
        return view('admins.all' , ['admins' => $admins]);
    }
public function admins_edit($id)
    {
        $admins = User::select('*')->where('id',$id)->get();
    abort_if(count($admins) == 0 ,404);
    
    $admin_info = $admins[0];
        return view('admins.edit' , ['admin_info' => $admin_info]);
    }
public function admins_remove ($id)
    {
        $admins = User::select('*')->where('id',$id)->get();
    abort_if(count($admins) == 0 ,404);
    
    User::select('*')->where('id',$id)->delete();
        return redirect()->route('site.admins');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function admins_save_update(Request $request){
        $id = $request->admin_id;
        
        
        
                $admins = User::select('*')->where('id',$id)->get();
    abort_if(count($admins) == 0 ,404);
    
    $admin_info = $admins[0];
        
        $update = User::select('*')->where('id',$id)->update([
"name" => $request->name,
"email" => $request->email,
"commission" => $request->commission,
"status" => $request->status,
"account_type" => $request->account_type,
        ]);
        
        
                if($request->password == null){
           
        }else{
           $updateUser = User::select('*')->where('id', $id)->update([
            "password" => \Hash::make($request->password),
        ]); 
            Auth::logout();
             return redirect()->route('site.index');
        }
        
        return redirect()->route('site.admins_edit' , $id); 
    }
    
    public function admins_create(){
        return view('admins.create');
    }
    public function admins_save(Request $request){
        $create = User::create([
"name" => $request->name,
"email" => $request->email,
"commission" => $request->commission,
"status" => $request->status,
"account_type" => $request->account_type,
"password" => \Hash::make($request->password),
"user_id" => "FX_" . rand(),
        ]);
        
        return redirect()->route('site.admins');
    }
    public function save(Request $request)
    {
                $id = $request->admin_id;
         $getUser = User::select('*')->where('id', $id)->get();
         abort_if(count($getUser) == 0, 404);
        $getUser = $getUser[0];
         
        $updateUser = User::select('*')->where('id', $id)->update([
            "name" => $request->name,
            "email" => $request->email,
//            "is_admin" => $request->is_admin,
        ]);
        
        if($request->password == null){
           
        }else{
           $updateUser = User::select('*')->where('id', $id)->update([
            "password" => \Hash::make($request->password),
        ]); 
            Auth::logout();
             return redirect()->route('site.index');
        }
        
        return redirect()->route('site.my_account');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
