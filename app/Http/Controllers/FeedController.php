<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostComment;
use App\Models\UserRelation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Intervention\Image\ImageManager;

class FeedController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = Auth::user();
    }

    public function post(Request $request)
    {
        $array = [
            'error' => ''
        ];

        $data = $request->only([
            'type',
            'body',
            'photo'
        ]);

        $validator = Validator::make($data, [
            'type' => ['required', 'string', Rule::in(array('text', 'photo'))],
            'body' => [
                Rule::requiredIf($data['type'] === 'text'),
                'exclude_if:type,photo',
                'string'
            ],
            'photo' => [
                Rule::requiredIf($data['type'] === 'photo'),
                'exclude_if:type,text',
                'image',
                'max:10000',
                'mimes:jpeg,jpg,png,webp'
            ]
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $type = $data['type'];

            do {
                $publicId = $this->generateUuid();
            } while (Post::where('public_id', $publicId)->count() !== 0);

            $newPost = new Post();
            $newPost->public_id = $publicId;
            $newPost->id_user = $this->loggedUser['public_id'];
            $newPost->type = $type;
            $newPost->created_at = gmdate('Y-m-d H:i:s');

            switch ($type) {
                case 'text':
                    $body = $data['body'];
                    $newPost->body = $body;
                    break;
                case 'photo':
                    $photo = $data['photo'];

                    $filename = $publicId . '.webp';

                    $destPath = public_path('/media/uploads/') . '/' . $filename;

                    $manager = new ImageManager();

                    $manager->make($photo->path())->resize(800, null, function ($constraint) {
                        $constraint->aspectRatio();
                    })->save($destPath);

                    $newPost->body = $filename;
                    break;
            };

            $newPost->save();

            $array['post'] = $newPost;

            if ($array['post']['type'] === 'photo') {
                $array['post']['body'] = url('media/uploads/' . $array['post']['body']);
            };

            $userInfo = User::where('public_id', $newPost->id_user)->first();

            $array['post']['mine'] = true;

            if ($userInfo) {
                $userInfo['avatar'] = url('media/avatars/' . $userInfo['avatar']);
                $userInfo['cover'] = url('media/covers/' . $userInfo['cover']);
                $array['post']['user'] = $userInfo;
            };

            $array['post']['likeCount'] = 0;
            $array['post']['liked'] = false;
            $array['post']['comments'] = [];
        };

        return $array;
    }

    public function read(Request $request)
    {
        $array = [
            'error' => ''
        ];

        $page = intval($request->input('page'));

        $perPage = 10;

        $users = [];

        $userList = UserRelation::where('user_from', $this->loggedUser['public_id'])
            ->get();

        foreach ($userList as $userItem) {
            $users[] = $userItem['user_to'];
        };

        $users[] = $this->loggedUser['public_id'];

        $total = Post::whereIn('id_user', $users)->count();
        $pageCount = ceil($total / $perPage);

        if ($page >= $pageCount) {
            $page = $pageCount - 1;
        };

        $postList = Post::whereIn('id_user', $users)->orderBy('created_at', 'desc')
            ->offset($page * $perPage)->limit($perPage)->get();

        $posts = $this->postListToObject($postList);

        $array['posts'] = $posts;
        $array['pageCount'] = $pageCount;
        $array['currentPage'] = $page;

        return $array;
    }

    public function userFeed(Request $request, $id = false)
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
            $page = intval($request->input('page'));

            $perPage = 10;

            $total = Post::where('id_user', $id)->count();
            $pageCount = ceil($total / $perPage);

            if ($page >= $pageCount) {
                $page = $pageCount - 1;
            };

            $postList = Post::where('id_user', $id)->orderBy('created_at', 'desc')
                ->offset($page * $perPage)->limit($perPage)->get();

            $posts = $this->postListToObject($postList);

            $array['posts'] = $posts;
            $array['pageCount'] = $pageCount;
            $array['currentPage'] = $page;
        };

        return $array;
    }

    private function postListToObject(Collection $postList)
    {
        $idLogged = $this->loggedUser['public_id'];

        foreach ($postList as $postKey => $postItem) {
            if ($postItem['type'] === 'photo') {
                $postItem['body'] = url('media/uploads/' . $postItem['body']);
            };

            if ($postItem['id_user'] === $idLogged) {
                $postList[$postKey]['mine'] = true;
            } else {
                $postList[$postKey]['mine'] = false;
            };

            $userInfo = User::where('public_id', $postItem['id_user'])->first();

            if ($userInfo) {
                $userInfo['avatar'] = url('media/avatars/' . $userInfo['avatar']);
                $userInfo['cover'] = url('media/covers/' . $userInfo['cover']);
                $postList[$postKey]['user'] = $userInfo;
            };

            $likes = PostLike::where('id_post', $postItem['public_id'])->count();

            $postList[$postKey]['likeCount'] = $likes;

            $isLiked = PostLike::where('id_post', $postItem['public_id'])
                ->where('id_user', $idLogged)->count();

            $postList[$postKey]['liked'] = ($isLiked > 0) ? true : false;

            $comments = PostComment::where('id_post', $postItem['public_id'])
                ->get();

            foreach ($comments as $commentKey => $comment) {
                $user = User::where('public_id', $comment['id_user'])->first();

                if ($user) {
                    $user['avatar'] = url('media/avatars/' . $user['avatar']);
                    $user['cover'] = url('media/covers/' . $user['cover']);
                    $comments[$commentKey]['user'] = $user;
                };
            };

            $postList[$postKey]['comments'] = $comments;
        };

        return $postList;
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
