<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $primaryKey = 'product_id'; // ADD THIS LINE

    protected $fillable = ['name', 'price', 'barcode', 'quantity_in_stock', 'expiry_date', 'supplier_id'];
}