<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceProfile extends Model
{
    protected $table = 'face_attendance_employees';

    protected $guarded = [];

    public function samples() { return $this->hasMany(FaceSample::class,'face_profile_id'); }

    protected $hidden = ['face_embedding'];

    protected function casts(): array
    {
        return ['face_embedding' => 'array'];
    }
}
