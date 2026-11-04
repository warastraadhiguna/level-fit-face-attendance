<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Data pegawai dikelola melalui Staff → Data Pegawai. User dan trainer tidak diimpor.');
    }
}
