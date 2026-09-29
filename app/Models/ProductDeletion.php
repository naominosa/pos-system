<?php
// app/Models/ProductDeletion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDeletion extends Model
{
    protected $primaryKey = 'deletion_id';
    public $timestamps = false;
    protected $fillable = ['product_name', 'barcode', 'deleted_by'];
}