<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'travelId' => ['required', 'string'],
            'orientation' => ['required', 'string', 'in:horizontal,vertical'],
            'type' => ['required', 'string', 'in:matrix'],
        ];
    }
}
