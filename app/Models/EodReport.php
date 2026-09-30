<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EodReport extends Model
{
    protected $primaryKey = 'eod_id';

    protected $fillable = [
        'staff_id', 'role', 'report_date',
        'total_sales', 'total_transactions',
        'satisfied_customers', 'notes',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}