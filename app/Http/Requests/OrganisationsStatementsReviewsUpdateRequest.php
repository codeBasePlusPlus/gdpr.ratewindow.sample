<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrganisationsStatementsReviewsUpdateRequest extends FormRequest
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
            'statement_id' => ['required', 'exists:statements,id'],
            'review_status_id' => ['required', 'exists:review_statuses,id'],
            'review' => ['required']
        ];
    }
}
