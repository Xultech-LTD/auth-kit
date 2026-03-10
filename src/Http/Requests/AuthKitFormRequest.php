<?php

namespace Xul\AuthKit\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * AuthKitFormRequest
 *
 * Base form request for AuthKit HTTP validation flows.
 *
 * Responsibilities:
 * - Preserve Laravel's native validation redirect behavior for standard
 *   server-rendered form submissions.
 * - Return standardized AuthKit DTO validation responses for JSON and AJAX
 *   consumers.
 * - Transform Laravel validator messages into structured AuthKitError items.
 * - Expose grouped field validation messages through payload.fields to simplify
 *   frontend form integrations.
 *
 * JSON validation response contract:
 * - ok: false
 * - status: 422
 * - message: validation summary message
 * - flow: failed
 * - payload.fields: grouped validation messages keyed by field name
 * - errors: flat structured validation error list
 *
 * Design notes:
 * - This class intentionally overrides only failed validation behavior.
 * - Standard Laravel authorization and redirect semantics remain untouched.
 * - SSR consumers continue to receive Laravel's flashed error bag and old input.
 * - JSON consumers receive a package-standardized response envelope that matches
 *   AuthKit action results.
 */
abstract class AuthKitFormRequest extends FormRequest
{
    /**
     * Handle failed validation.
     *
     * Behavior:
     * - For standard web requests, defer to Laravel's default FormRequest
     *   behavior so redirects, flashed input, and error bags continue to work.
     * - For JSON or AJAX requests, throw a standardized AuthKit DTO response.
     *
     * Validation payload format:
     * - payload.fields contains grouped messages in Laravel's native
     *   field-to-messages shape.
     * - errors contains flattened structured validation errors for
     *   programmatic consumers.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        if (! ResponseResolver::expectsJson($this)) {
            parent::failedValidation($validator);

            return;
        }

        $fieldMap = $validator->errors()->toArray();
        $errors = [];

        foreach ($fieldMap as $field => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = AuthKitError::validation(
                    field: (string) $field,
                    message: (string) $message,
                );
            }
        }

        $result = AuthKitActionResult::validationFailure(
            errors: $errors,
            fields: $fieldMap,
        );

        throw new HttpResponseException(
            response()->json($result->toArray(), $result->status)
        );
    }
}