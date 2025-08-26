<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use CrudTrait;
    public function package()
    {
        return $this->belongsTo(Package::class);
    }
    //
}
