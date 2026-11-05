<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        $schema=Schema::connection(config('database.default'));
        if (!$schema->hasColumn('employees','born')) {
            $schema->table('employees',function(Blueprint $t) { $t->date('born')->nullable(); });
        }
    }
    public function down(): void { /* Retain employee dates of birth on rollback. */ }
};
