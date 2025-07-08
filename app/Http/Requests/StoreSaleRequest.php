<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_id' => 'nullable|exists:sale_rooms,id',
            'room_temp_id' => 'required|exists:room_temps,id',
            'master_room' => 'required|exists:room_numbers,id',
            'rooms' => 'required|array',
            'rooms.*' => 'integer',
            'confirmation_date' => 'required|date',
            'reservation_type' => 'required',
            'pax' => 'required|integer',
            'eta' => 'required',
            'etd' => 'required',
            'remark' => 'nullable',
            'date_checkin' => 'required',
            'date_checkout' => 'required',
            'vat_per' => 'nullable',
            'service_charge_per' => 'nullable',
            'discount_type' => 'required|in:invoice,category,none',
            'discount_per' => 'nullable|numeric|required_if:discount_type,invoice',
            'categories' => 'nullable|array|required_if:discount_type,category|min:1',
            'xbs' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'discount_per.required_if' => 'Discount percentage is required when discount type is invoice.',
            'categories.required_if' => 'Categories are required when discount type is category.',
            'categories.min' => 'Please select at least one category.',
        ];
    }
}
