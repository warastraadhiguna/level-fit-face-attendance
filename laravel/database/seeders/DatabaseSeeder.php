<?php
namespace Database\Seeders;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('users as u')->leftJoin('branch_stores as b', 'u.branch_store_id', '=', 'b.id')
            ->whereNull('u.deleted_at')->orderBy('u.id')
            ->select('u.id', 'u.full_name', 'u.role', 'b.name as branch_name')->get();
        foreach ($users as $user) {
            Employee::updateOrCreate(['employee_code'=>'USER-'.$user->id], [
                'source_user_id'=>$user->id,
                'name'=>$user->full_name,
                'department'=>$user->role.($user->branch_name ? ' · '.$user->branch_name : ''),
            ]);
        }
        $this->command?->info('Synchronized '.$users->count().' active users from level-fit.');
    }
}
