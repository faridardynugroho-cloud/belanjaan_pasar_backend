<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxYear = (int) now()->addYear()->format('Y');

        return [
            // Data pribadi
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['required', 'date', 'before:today'],
            'domicile_address' => ['required', 'string', 'max:1000'],

            // Identitas & SIM
            'nik' => ['required', 'digits:16', 'unique:driver_documents,nik'],
            'ktp_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'selfie_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'sim_type' => ['required', Rule::in(['C', 'c'])],
            'sim_number' => ['required', 'string', 'max:20'],
            'sim_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'sim_expired_at' => ['required', 'date', 'after:today'],

            // Kendaraan
            'vehicle_type' => ['required', 'string', 'max:50'],
            'vehicle_brand' => ['required', 'string', 'max:50'],
            'vehicle_model' => ['required', 'string', 'max:50'],
            'vehicle_plate' => ['required', 'string', 'max:20', 'unique:driver_vehicles,vehicle_plate'],
            'vehicle_color' => ['required', 'string', 'max:30'],
            'production_year' => ['required', 'integer', 'min:1990', 'max:'.$maxYear],
            'vehicle_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'stnk_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],

            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
