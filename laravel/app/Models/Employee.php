<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'face_attendance_employees';

    protected $guarded = [];

    protected $hidden = ['face_embedding'];

    protected function casts(): array
    {
        return ['face_embedding' => 'array'];
    }
}
