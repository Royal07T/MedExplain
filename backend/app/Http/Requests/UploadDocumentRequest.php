<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Validation is defense-in-depth: the MIME types rule inspects the actual
     * file contents, while the mimes rule validates the extension, and the
     * size rule enforces the 10 MB upload limit.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpeg,jpg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:10240',
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please attach a medical document.',
            'file.mimes' => 'The document must be a PDF, JPG, or PNG file.',
            'file.mimetypes' => 'The document must be a PDF, JPG, or PNG file.',
            'file.max' => 'The document must be 10 MB or smaller.',
        ];
    }
}