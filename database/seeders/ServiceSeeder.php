<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\ManagedService;
use App\Models\ServiceOption;
use Illuminate\Database\Seeder;
use RuntimeException;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 5) as $number) {
            $groupName = "group-{$number}";
            $group = Group::where('name', $groupName)->first();

            if (!$group) {
                throw new RuntimeException(
                    "Group {$groupName} belum tersedia. Jalankan GroupSeeder sebelum ServiceSeeder."
                );
            }

            $service = ManagedService::updateOrCreate(
                [
                    'code' => "service_{$number}",
                ],
                [
                    'group_id' => $group->id,
                    'name' => "Layanan {$number}",
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $number,
                ]
            );

            foreach (range(1, 5) as $optionNumber) {
                ServiceOption::updateOrCreate(
                    [
                        'service_id' => $service->id,
                        'sort_order' => $optionNumber,
                    ],
                    [
                        'name' => "Opsi {$optionNumber}",
                        'description' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
