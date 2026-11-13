<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees','all_branch')) {
            Schema::table('employees',function(Blueprint $t) { $t->boolean('all_branch')->default(false); });
        }
    }
    public function down(): void { /* Retain the employee branch-access setting on rollback. */ }
};
