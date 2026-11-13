<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Employee extends Model
{
    use SoftDeletes;

    protected $fillable=['employee_code','branch_store_id','full_name','born','position','phone','is_active','all_branch'];
    protected $casts=['is_active'=>'boolean','all_branch'=>'boolean','born'=>'date'];

    public function scopeAvailableInBranch($query, int $branch)
    {
        return $query->where(function($scope) use($branch) {
            $scope->where('branch_store_id',$branch)->orWhere('all_branch',true);
        });
    }
}
