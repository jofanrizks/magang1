<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $groups = $user && $user->role === 'user'
            ? $user->groups()
                ->select('groups.id', 'groups.name')
                ->get()
            : Group::query()
                ->select('id', 'name')
                ->get();

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    public function show(Group $group)
    {
        $group->setRelation(
            'users',
            $group->users()->get([
                'users.id',
                'nik',
                'nama',
                'instansi',
                'jabatan',
                'sts',
                'approval',
            ])
        );

        return response()->json([
            'success' => true,
            'data' => $group
        ]);
    }
}
