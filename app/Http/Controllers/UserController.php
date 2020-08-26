<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getPoints($id)
    {
        $user = User::where('id', $id)->first();
        return response()->json([
            'data' => [
                'points' => $user->points,
                'level' => $user->level,
            ],
        ]);
    }
}
