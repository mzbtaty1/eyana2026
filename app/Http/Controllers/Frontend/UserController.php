<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Validation\Rule;
use App\Support\Permissions;
use App\Models\User;
use App\Models\Password;
use App\Http\Requests\StorePasswordRequest;
use App\Http\Requests\UpdatePasswordEntryRequest;
class UserController extends Controller
{
    public function __construct()
    {
        // server-side authorization (the routes carry the same 'can:' middleware)
        $this->middleware('can:' . Permissions::EMPLOYEES_MANAGE)->only(['admins', 'admins_edit', 'admins_remove', 'admins_save_update', 'admins_create', 'admins_save']);
        $this->middleware('can:' . Permissions::SETTINGS_MANAGE)->only(['password', 'password_create', 'password_save', 'password_edit', 'password_update', 'password_delete']);
    }

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
public function password_save  (StorePasswordRequest $request)
    {

        $create = Password::create([
            "url" => $request->url,
            "user" => $request->user,
            "pass" => $request->pass,
        ]);
    return redirect()->route('site.password');
    }

    public function password_edit($id)
    {
        $password = Password::select('*')->where('id', $id)->get();
        abort_if(count($password) == 0, 404);

        return view('passwords.edit', ['password_info' => $password[0]]);
    }

    public function password_update(UpdatePasswordEntryRequest $request)
    {
        $id = (int) $request->id;
        $password = Password::select('*')->where('id', $id)->get();
        abort_if(count($password) == 0, 404);

        Password::where('id', $id)->update([
            "url" => $request->url,
            "user" => $request->user,
            "pass" => $request->pass,
        ]);

        return redirect()->route('site.password');
    }

    public function password_delete($id)
    {
        $password = Password::select('*')->where('id', $id)->get();
        abort_if(count($password) == 0, 404);

        Password::where('id', $id)->delete();

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

    /**
     * Delete a user (admin only, POST + CSRF). An admin cannot delete their own account.
     */
    public function admins_remove($id)
    {
        $user = User::find((int) $id);
        abort_if(!$user, 404);
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()->route('site.admins')->withErrors(['user' => 'لا يمكنك حذف حسابك الحالي']);
        }

        $user->delete();
        return redirect()->route('site.admins');
    }

    /** Validation of the admin user form (create: password required). The login («email») is a
     * username for many existing users, so it is only required and unique -- not an email format. */
    private function userRules(?int $id, bool $create): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'commission' => ['required', 'string', 'max:20'],
            'status' => ['required', 'in:0,1'],
            'account_type' => ['required', 'in:1,2'],
            'password' => [$create ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Admin edit of a user (the user comes from admin_id: this whole screen is admin only).
     * An admin cannot change their own account type or status (no self lock-out). Changing
     * another user's password keeps the admin signed in; changing one's own signs out, as before.
     */
    public function admins_save_update(Request $request){
        $admin_info = User::find((int) $request->admin_id);
        abort_if(!$admin_info, 404);
        $data = $request->validate($this->userRules($admin_info->id, false));
        $self = (int) $admin_info->id === (int) Auth::id();

        User::where('id', $admin_info->id)->update([
            "name" => $data['name'],
            "email" => $data['email'],
            "commission" => $data['commission'],
            "status" => $self ? $admin_info->status : $data['status'],
            "account_type" => $self ? $admin_info->account_type : $data['account_type'],
        ]);

        if (!empty($data['password'])) {
            User::where('id', $admin_info->id)->update([
                "password" => \Hash::make($data['password']),
            ]);
            if ($self) {
                Auth::logout();
                return redirect()->route('site.index');
            }
        }

        return redirect()->route('site.admins_edit' , $admin_info->id);
    }

    public function admins_create(){
        return view('admins.create');
    }
    public function admins_save(Request $request){
        $data = $request->validate($this->userRules(null, true));
        User::create([
            "name" => $data['name'],
            "email" => $data['email'],
            "commission" => $data['commission'],
            "status" => $data['status'],
            "account_type" => $data['account_type'],
            "password" => \Hash::make($data['password']),
            "user_id" => "FX_" . rand(),
        ]);

        return redirect()->route('site.admins');
    }

    /**
     * «حسابي»: self-service. Always the signed-in user (a posted admin_id is ignored), and
     * only name, email and password -- never account type, commission, status or permissions.
     */
    public function save(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        User::where('id', $user->id)->update([
            "name" => $data['name'],
            "email" => $data['email'],
        ]);

        if (!empty($data['password'])) {
            User::where('id', $user->id)->update([
                "password" => \Hash::make($data['password']),
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
