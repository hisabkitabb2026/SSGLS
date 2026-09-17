<?php

namespace App\Domains\Settings\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $fillable = ['name', 'type', 'company_id'];

    public function company()
    {
        return $this->belongsTo(related: Company::class);
    }
}
