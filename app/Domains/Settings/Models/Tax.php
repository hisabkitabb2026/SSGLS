<?php

namespace App\Domains\Settings\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;

    protected $table = 'taxes';

    protected $fillable = ['name', 'rate', 'company_id'];

    protected $casts = ['rate' => 'float'];

    public function company()
    {
        return $this->belongsTo(related: Company::class);
    }
}
