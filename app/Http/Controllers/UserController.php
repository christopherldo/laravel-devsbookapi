<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        // $this->middleware('auth:api');
        // $this->loggedUser = auth()->user();
    }

    public function create(Request $request)
    {
        $array = [
            'error' => ''
        ];

        $data = $request->only([
            'name',
            'email',
            'password',
            'birthdate'
        ]);

        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $birthdate = $data['birthdate'] ?? '';

        if ($name && $email && $password && $birthdate) {
            if (strtotime($birthdate) === false) {
                $array['error'] = 'Invalid Birthdate';
            } else {
                if (User::where('email', $email)->count() !== 0) {
                    $array['error'] = 'E-mail already been taken';
                } else {
                    $validator = Validator::make($data, [
                        'email' => 'string|email|max:50'
                    ]);

                    if ($validator->fails()) {
                        $array['error'] = 'Invalid e-mail';
                    } else {
                        $validator = Validator::make($data, [
                            'password' => 'string|min:8'
                        ]);

                        if ($validator->fails()) {
                            $array['error'] = 'Password must be 8 chars longs at least';
                        } else {
                            do {
                                $salt = $this->generateSalt();
                            } while (User::where('salt', $salt)->count() !== 0);

                            do {
                                $publicId = $this->generateUuid();
                            } while (User::where('public_id', $publicId)->count() !== 0);

                            $sha256 = hash('sha256', $password . $salt);
                            $hash = password_hash($sha256, PASSWORD_DEFAULT);

                            $newUser = new User();
                            $newUser->public_id = $publicId;
                            $newUser->email = $email;
                            $newUser->password = $hash;
                            $newUser->salt = $salt;
                            $newUser->name = $name;
                            $newUser->birthdate = $birthdate;
                            $newUser->save();

                            $token = Auth::attempt([
                                'email' => $email,
                                'password' => hash('sha256', $password . $salt)
                            ]);

                            if ($token) {
                                $array['token'] = $token;
                            } else {
                                $array['error'] = 'Unexpected error!';
                            };
                        };
                    };
                };
            };
        } else {
            $array['error'] = 'You must fill all fields';
        };

        return $array;
    }

    private function generateSalt(int $length = 64)
    {
        $chars =  'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . 'abcdefghijklmnopqrstuvwxyz' .
            '0123456789' . '`-=~!@#$%^&*()_+,./<>?;:[]{}\|';
        $salt = '';

        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $salt .= $chars[random_int(0, $max)];
        }

        return $salt;
    }

    private function generateUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
