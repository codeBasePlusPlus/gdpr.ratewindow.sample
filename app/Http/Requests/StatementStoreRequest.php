<?php

namespace App\Http\Requests;

use App\Models\Statement;
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
            'implementation_en' => ['required'],
            'implementation_se' => ['required'],
            'guide_en' => ['required'],
            'guide_se' => ['required'],
            'component_id' => ['required', 'exists:components,id'],
            'code' => ['required', 'integer', 'gt:0'],
            'statement_type_id' => ['required', 'exists:statement_types,id'],
            'sort_order' => ['required', 'integer', 'unique:statements']
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $componentId = $this->input('component_id');
            $code = $this->input('code');

            $subCodeExists = Statement::where(['component_id' => $componentId, 'code' => $code])->exists();

            if ($subCodeExists) {
                $validator->errors()->add('code', 'Subcode exists.');
            }
        });
    }
}
