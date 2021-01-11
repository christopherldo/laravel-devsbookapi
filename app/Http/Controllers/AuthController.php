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
                'create',
                'unauthorized'
            ]
        ]);
    }

    public function unauthorized()
    {
    }
}
