<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserRelation;
use App\Models\Post;
use DateTime;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'create'
            ]
        ]);
        $this->loggedUser = Auth::user();
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
            'password_confirmation',
            'birthdate'
        ]);

        $validator = Validator::make($data, [
            'name' => 'required|string|max:50',
            'email' => 'required|string|email|max:50|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
            'birthdate' => 'required|date|before_or_equal:' . gmdate('Y-m-d', strtotime('-13 years'))
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $name = $data['name'];
            $email = $data['email'];
            $password = $data['password'];
            $birthdate = Date('Y-m-d', strtotime($data['birthdate']));

            do {
                $publicId = $this->generateUuid();
            } while (User::where('public_id', $publicId)->count() !== 0);

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $newUser = new User();
            $newUser->public_id = $publicId;
            $newUser->email = $email;
            $newUser->password = $hash;
            $newUser->name = $name;
            $newUser->birthdate = $birthdate;
            $newUser->avatar = url('media/avatars' . $newUser->avatar);
            $newUser->cover = url('media/avatars' . $newUser->cover);
            $newUser->save();

            $token = Auth::attempt([
                'email' => $email,
                'password' => $password
            ]);

            if ($token) {
                $array['token'] = $token;
                $array['user'] = $newUser;
            } else {
                $array['error'] = 'Unexpected error!';
            };
        }

        return $array;
    }

    public function update(Request $request)
    {
        $array = [
            'error' => ''
        ];

        $data = $request->only([
            'name',
            'email',
            'birthdate',
            'city',
            'work',
            'password',
            'password_confirmation'
        ]);

        $user = User::find($this->loggedUser['id']);

        $validator = Validator::make($data, [
            'name' => 'string|max:50',
            'email' => 'string|email|max:50',
            'birthdate' => 'date|before_or_equal:' . gmdate('Y-m-d', strtotime('-13 years')),
            'city' => 'string|max:50',
            'work' => 'string|max:50',
            'password' => 'string|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $email = $data['email'] ?? '';
            $name = $data['name'] ?? '';
            $city = $data['city'] ?? '';
            $work = $data['work'] ?? '';
            $password = $data['password'] ?? '';
            $birthdate = isset($data['birthdate']) ?
                Date('Y-m-d', strtotime($data['birthdate'])) : '';

            if ($user->email !== $email) {
                if (User::where('email', $email)->count() > 0) {
                    $array['error'] = 'The email has already been taken.';
                } else {
                    if ($email) {
                        $user->email = $email;
                    };
                };
            };

            if ($name) {
                $user->name = $name;
            };

            if ($city) {
                $user->city = $city;
            };

            if ($work) {
                $user->work = $work;
            };

            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $user->password = $hash;
            };

            if ($birthdate) {
                $user->birthdate = $birthdate;
            };

            $user->save();

            $array['user'] = $user;
        }

        return $array;
    }

    public function updateAvatar(Request $request)
    {
        $array = [
            'error' => ''
        ];

        $data = $request->only(['avatar']);

        $validator = Validator::make($data, [
            'avatar' => 'required|image|max:10000|mimes:jpeg,jpg,png,webp'
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $image = $data['avatar'];

            $user = User::find($this->loggedUser['id']);

            $filename = $user->public_id . '.webp';

            $destPath = public_path('/media/avatars') . '/' . $filename;

            $manager = new ImageManager();

            $manager->make($image->path())->fit(200, 200)->save($destPath);

            $user->avatar = $filename;
            $user->save();

            $array['url'] = url('/media/avatars/' . $filename);
        };

        return $array;
    }

    public function updateCover(Request $request)
    {
        $array = [
            'error' => ''
        ];

        $data = $request->only(['cover']);

        $validator = Validator::make($data, [
            'cover' => 'required|image|max:10000|mimes:jpeg,jpg,png,webp'
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $image = $data['cover'];

            $user = User::find($this->loggedUser['id']);

            $filename = $user->public_id . '.webp';

            $destPath = public_path('/media/covers') . '/' . $filename;

            $manager = new ImageManager();

            $manager->make($image->path())->fit(850, 310)->save($destPath);

            $user->cover = $filename;
            $user->save();

            $array['url'] = url('/media/covers/' . $filename);
        };

        return $array;
    }

    public function read($id = false)
    {
        $array = [
            'error' => ''
        ];

        if ($id) {
            $validator = Validator::make(['public_id' => $id], [
                'public_id' => 'uuid|exists:users'
            ]);
        } else {
            $id = $this->loggedUser['public_id'];
        };

        if (isset($validator) && $validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $user = User::where('public_id', $id)->first();

            $me = ($user['public_id'] === $this->loggedUser['public_id']) ?
                true : false;

            $dateFrom = new DateTime($user->birthdate);
            $dateTo = new DateTime(gmdate('Y-m-d'));
            $age = $dateFrom->diff($dateTo)->y;

            $followers = UserRelation::where('user_to', $user->public_id)
                ->count();
            $following = UserRelation::where('user_from', $user->public_id)
                ->count();

            $photoCount = Post::where('id_user', $user->public_id)
                ->where('type', 'photo')->count();

            $array['info'] = [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'birthdate' => $user->birthdate,
                'city' => $user->city,
                'work' => $user->work,
                'avatar' => url('/media/avatars/' . $user->avatar),
                'cover' => url('/media/covers/' . $user->cover),
                'me' => $me,
                'age' => $age,
                'followers' => $followers,
                'following' => $following,
                'photo_count' => $photoCount
            ];

            $hasRelation = UserRelation::where('user_from', $this->loggedUser['public_id'])
                ->where('user_to', $user->public_id)->first();

            if ($this->loggedUser['public_id'] !== $user->public_id) {
                $array['info']['is_following'] = ($hasRelation) ? true : false;
            };
        };

        return $array;
    }

    public function follow(string $id)
    {
        $array = [
            'error' => ''
        ];

        $validator = Validator::make(['public_id' => $id], [
            'public_id' => 'required|uuid|exists:users|' .
                Rule::notIn([$this->loggedUser['public_id']])
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $relation = UserRelation::where('user_from', $this->loggedUser['public_id'])
                ->where('user_to', $id)->first();

            if ($relation) {
                $relation->delete();

                $array['relation'] = [
                    'user_from' => $this->loggedUser['public_id'],
                    'user_to' => $id,
                    'following' => false
                ];
            } else {
                $newRelation = new UserRelation();
                $newRelation->user_from = $this->loggedUser['public_id'];
                $newRelation->user_to = $id;
                $newRelation->save();

                $array['relation'] = [
                    'user_from' => $newRelation->user_from,
                    'user_to' => $newRelation->user_to,
                    'following' => true
                ];
            };
        };

        return $array;
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
