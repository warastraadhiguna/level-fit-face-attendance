<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'face_attendance_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime', 'face_verified' => 'boolean'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
