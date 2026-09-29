<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $primaryKey = 'sale_item_id'; // matches your real table too

    protected $fillable = ['sale_id', 'product_id', 'quantity', 'price_at_time_of_sale'];
}