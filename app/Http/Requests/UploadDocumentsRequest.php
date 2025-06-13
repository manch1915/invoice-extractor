<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => [
                'file',
                'max:'.config('invoices.upload.max_kb'),
                'mimes:'.implode(',', config('invoices.upload.mimes')),
            ],
        ];
    }
}
