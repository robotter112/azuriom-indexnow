<?php

namespace Azuriom\Plugin\IndexnowDemo\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'indexnow_demo_items';

    protected $fillable = ['name'];
}
