<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Employee extends Model
{
    use SoftDeletes;

    protected $fillable=['employee_code','branch_store_id','full_name','position','phone','is_active'];
    protected $casts=['is_active'=>'boolean'];
}
