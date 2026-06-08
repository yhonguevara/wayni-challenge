<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for file upload validation.
 *
 * Accepts TWO modes:
 * - Multipart: file field (required, file, mimes:txt, max:6291456 KB = 6GB)
 * - JSON: s3_key field (required, string)
 *
 * Validation: either file OR s3_key must be present (not both).
 */
final class UploadFileRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required_without:s3_key',
                'file',
                'mimes:txt',
                'mimetypes:text/plain',
                'max:6291456', // 6GB in KB
            ],
            's3_key' => [
                'required_without:file',
                'string',
                'max:1024',
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Either a file upload or s3_key is required.',
            's3_key.required_without' => 'Either a file upload or s3_key is required.',
            'file.mimes' => 'The file must be a .txt file.',
            'file.max' => 'The file must not exceed 6GB.',
        ];
    }

    /**
     * Check if the request contains a file upload.
     */
    public function hasUploadedFile(): bool
    {
        return parent::hasFile('file');
    }

    /**
     * Check if the request contains an S3 key.
     */
    public function hasS3Key(): bool
    {
        return $this->has('s3_key') && $this->input('s3_key') !== '';
    }
}
