<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatementStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
            'content_en' => ['required'],
            'content_se' => ['required'],
            'desc_en' => ['required'],
            'desc_se' => ['required'],
            'guide_en' => ['required'],
            'guide_se' => ['required'],
            'component_id' => ['required', 'exists:components,id'],
            'statement_type_id' => ['required', 'exists:statement_types,id'],
            'sort_order' => ['required', 'integer', 'unique:statements']
        ];
    }
}
