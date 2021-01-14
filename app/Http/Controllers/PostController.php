<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostComment;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;

class PostController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = Auth::user();
    }

    public function like(string $id)
    {
        $array = [
            'error' => ''
        ];

        $validator = Validator::make(['public_id' => $id], [
            'public_id' => 'required|uuid|exists:posts'
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $array['post']['public_id'] = $id;

            $isLiked = PostLike::where('id_post', $id)
                ->where('id_user', $this->loggedUser['public_id'])->first();

            if ($isLiked) {
                $isLiked->delete();

                $array['post']['is_liked'] = false;
            } else {
                $postLike = new PostLike();
                $postLike->id_post = $id;
                $postLike->id_user = $this->loggedUser['public_id'];
                $postLike->created_at = gmdate('Y-m-d H:i:s');
                $postLike->save();

                $array['post']['is_liked'] = true;
            };

            $likeCount = PostLike::where('id_post', $id)->count();

            $array['post']['like_count'] = $likeCount;
        };

        return $array;
    }

    public function comment(Request $request, string $id)
    {
        $array = [
            'error' => ''
        ];

        $data = $request->only(['txt']);
        $data['public_id'] = $id;

        $validator = Validator::make($data, [
            'txt' => 'required|string',
            'public_id' => 'required|uuid|exists:posts'
        ]);

        if ($validator->fails()) {
            $array['error'] = $validator->errors();
        } else {
            $body = $data['txt'];

            $comment = new PostComment();
            $comment->id_post = $id;
            $comment->id_user = $this->loggedUser['public_id'];
            $comment->created_at = gmdate('Y-m-d H:i:s');
            $comment->body = $body;
            $comment->save();

            $array['comment'] = $comment;

            $array['comment']['name'] = $this->loggedUser['name'];

            $array['comment']['avatar'] = url(
                '/media/avatars/' . $this->loggedUser['avatar']
            );
        };

        return $array;
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
            'type' => 'required|string|' . Rule::in(array('text', 'photo')),
            'body' => 'exclude_if:type,photo|string|' .
                Rule::requiredIf(isset($data['type']) && $data['type'] === 'text'),
            'photo' => 'exclude_if:type,text|image|max:10000|mimes:jpeg,jpg,png,webp' .
                Rule::requiredIf(isset($data['type']) && $data['type'] === 'photo')
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
}
