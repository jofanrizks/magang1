<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1,5) as $i) {

            Group::create([

                'name' => "group-$i"

            ]);

        }
    }
}