<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxType extends Model
{
    use HasFactory;

    protected $table = 'tax_types';

    protected $fillable = ['name', 'code'];
}
