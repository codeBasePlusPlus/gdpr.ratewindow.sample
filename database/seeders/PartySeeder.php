<?php

namespace Database\Seeders;

use App\Models\Sanction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PartySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*
        $oldSanctions = File::get(base_path('/resources/SANKTION.json'));
        $oldSanctions = collect(json_decode($oldSanctions));

        $newSanctions = Sanction::all();

        $oldSanctions->each(function ($oldSanction) use ($newSanctions) {
            $oldSanctionTitle = $oldSanction->sanktion_id_namn;
            $newSanction = $newSanctions->where('title', $oldSanctionTitle)->first();

            if ($newSanction) {
                $oldSanctionParty = trim($oldSanction->Party_Name_1);
                $newSanction->update(['party' => $oldSanctionParty]);
            }
        });
        */
    }
}
