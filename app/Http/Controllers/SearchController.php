<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class SearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function search(Request $request)
    {
        $array = [
            'error' => '',
        ];

        $txt = $request->input('txt');

        $validator = Validator::make(['txt' => $txt], [
            'txt' => 'required'
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $array['users'] = [];

            $userList = User::where('name', 'LIKE', '%' . $txt . '%')->get();

            foreach ($userList as $userItem) {
                $array['users'][] = [
                    'public_id' => $userItem['public_id'],
                    'name' => $userItem['name'],
                    'avatar' => url('/media/avatars/' . $userItem['avatar'])
                ];
            };
        };

        return $array;
    }
}
