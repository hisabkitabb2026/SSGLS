<?php

namespace App\Domains\Expense\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['category_id' => 'required|integer|exists:expense_categories,id', 'description' => 'required|string|max:255', 'amount' => 'required|numeric|min:0.01', 'date' => 'required|date'];
    }
}
