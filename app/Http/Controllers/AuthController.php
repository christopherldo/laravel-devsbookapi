<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'login',
                'unauthorized'
            ]
        ]);
    }

    public function unauthorized()
    {
        $array = [
            'error' => 'Unauthorized'
        ];
        return response()->json($array, 401);
    }

    public function login(Request $request)
    {
        $array = [
            'error' => ''
        ];

        $data = $request->only([
            'email',
            'password'
        ]);

        if (isset($data['email']) && isset($data['password'])) {
            $token = Auth::attempt($data);

            if ($token === false) {
                $array['error'] = 'Invalid email or password';
            } else {
                $array['token'] = $token;
            };
        } else {
            $array['error'] = 'You must fill all fields';
        }

        return $array;
    }

    public function logout()
    {
        $array = [
            'error' => ''
        ];

        Auth::logout();

        return $array;
    }

    public function refresh()
    {
        $array = [
            'error' => ''
        ];

        $token = Auth::refresh();
        $array['token'] = $token;

        return $array;
    }
}
