<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller {
    /**
     * Handle an authentication attempt.
     *
     * @return Response
     */
    public function authenticate(Request $request)
    {
        $account = $request->input('user');
        $password = $request->input('password');
        // if (Auth::attempt(['email' => $email, 'password' => $password])) {
        if (Auth::attempt(['account' => $account, 'password' => $password])) {
            // Authentication passed...
            // return redirect()->intended('dashboard');
            // 重定向操作由前端来做
            // 该处返回已登录用户的信息
            return Auth::user();
        }
    }

    public function logout() {
        if(Auth::check()) {
            Auth::logout();
        }
    }
}
?>