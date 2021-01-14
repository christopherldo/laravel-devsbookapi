<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostComment;
use App\Models\UserRelation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FeedController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = Auth::user();
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
                $postList[$postKey]['user'] = [
                    'public_id' => $userInfo->public_id,
                    'name' => $userInfo->name,
                    'avatar' => url('media/avatars/' . $userInfo->avatar),
                ];
            };

            $likes = PostLike::where('id_post', $postItem['public_id'])->count();

            $postList[$postKey]['like_count'] = $likes;

            $isLiked = PostLike::where('id_post', $postItem['public_id'])
                ->where('id_user', $idLogged)->count();

            $postList[$postKey]['is_liked'] = ($isLiked > 0) ? true : false;

            $comments = PostComment::where('id_post', $postItem['public_id'])
                ->get();

            foreach ($comments as $commentKey => $comment) {
                $user = User::where('public_id', $comment['id_user'])->first();

                if ($user) {
                    $comments[$commentKey]['user'] = [
                        'public_id' => $user->public_id,
                        'name' => $user->name,
                        'avatar' => url('media/avatars/' . $user->avatar),
                    ];
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
