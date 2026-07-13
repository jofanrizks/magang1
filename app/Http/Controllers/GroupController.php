<?php

namespace App\Http\Controllers;

use App\Models\Group;

class GroupController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Group::all()
        ]);
    }

    public function show(Group $group)
    {
        $group->setRelation(
            'users',
            $group->users()->get([
                'id',
                'group_id',
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
