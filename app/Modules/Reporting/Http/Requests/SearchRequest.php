<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Queries\Search;
use App\Support\Search\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ROUTE-281's input (WS-03; PF-23): the term in the request body — never in the address (DP-02) —, at most 100
 * characters; the result types a form's lookup offers, from the whitelist only.
 */
final class SearchRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:'.Term::MAX_LENGTH],
            'jenis' => ['sometimes', 'array', 'max:'.count(Search::TYPES)],
            'jenis.*' => ['string', Rule::in(Search::TYPES)],
        ];
    }

    public function term(): Term
    {
        return new Term($this->string('q')->toString());
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_values(array_filter((array) $this->input('jenis', []), 'is_string'));
    }
}
