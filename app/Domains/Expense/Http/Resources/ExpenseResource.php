<?php

namespace App\Domains\Expense\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray($request)
    {
        return ['id' => $this->id, 'category_id' => $this->category_id, 'description' => $this->description, 'amount' => $this->amount, 'date' => $this->date, 'created_at' => $this->created_at];
    }
}
