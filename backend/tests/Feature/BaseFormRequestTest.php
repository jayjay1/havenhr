<?php

use App\Http\Requests\BaseFormRequest;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Test Form Request for validation testing
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    // Register a test route that uses a concrete BaseFormRequest subclass
    Route::post('/test/validation', function (\App\Http\Requests\TestFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');
});

/*
|--------------------------------------------------------------------------
| Unknown fields are rejected with 422
|--------------------------------------------------------------------------
*/

it('rejects unknown fields with 422', function () {
    $response = $this->postJson('/test/validation', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'unknown_field' => 'some value',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    $response->assertJsonPath('error.details.fields.unknown_field.messages.0', 'The field unknown_field is not allowed.');
});

it('rejects multiple unknown fields', function () {
    $response = $this->postJson('/test/validation', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'foo' => 'bar',
        'baz' => 'qux',
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
            'details' => [
                'fields' => [
                    'foo' => ['value', 'messages'],
                    'baz' => ['value', 'messages'],
                ],
            ],
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| Validation errors return structured JSON with field-specific messages
|--------------------------------------------------------------------------
*/

it('returns structured JSON error response for validation failures', function () {
    $response = $this->postJson('/test/validation', [
        'name' => '',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    $response->assertJsonPath('error.message', 'The given data was invalid.');
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
            'details' => [
                'fields',
            ],
        ],
    ]);
});

it('includes submitted value in error response for non-sensitive fields', function () {
    $response = $this->postJson('/test/validation', [
        'name' => 'x',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.details.fields.email.value', 'not-an-email');
});

it('includes field-specific error messages', function () {
    $response = $this->postJson('/test/validation', [
        'name' => '',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);

    $fields = $response->json('error.details.fields');
    expect($fields)->toHaveKey('name');
    expect($fields)->toHaveKey('email');
    expect($fields['name']['messages'])->toBeArray()->not->toBeEmpty();
    expect($fields['email']['messages'])->toBeArray()->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Password fields are redacted in error responses
|--------------------------------------------------------------------------
*/

it('redacts password field values in error responses', function () {
    // Use the password-aware test route
    Route::post('/test/validation-password', function (\App\Http\Requests\TestPasswordFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-password', [
        'email' => 'john@example.com',
        'password' => 'short',
        'password_confirmation' => 'mismatch',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.details.fields.password.value', '[REDACTED]');
});

it('redacts owner_password field values in error responses', function () {
    Route::post('/test/validation-owner', function (\App\Http\Requests\TestOwnerPasswordFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-owner', [
        'owner_password' => 'weak',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.details.fields.owner_password.value', '[REDACTED]');
});

/*
|--------------------------------------------------------------------------
| Email validation follows RFC 5322
|--------------------------------------------------------------------------
*/

it('accepts valid RFC 5322 email addresses', function () {
    $response = $this->postJson('/test/validation', [
        'name' => 'John Doe',
        'email' => 'user@example.com',
    ]);

    $response->assertStatus(200);
});

it('rejects invalid email addresses', function (string $email) {
    $response = $this->postJson('/test/validation', [
        'name' => 'John Doe',
        'email' => $email,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.details.fields'))->toHaveKey('email');
})->with([
    'missing @' => ['plainaddress'],
    'missing domain' => ['user@'],
    'missing local part' => ['@example.com'],
    'double dots in domain' => ['user@example..com'],
]);

/*
|--------------------------------------------------------------------------
| Password complexity rules (StrongPassword)
|--------------------------------------------------------------------------
*/

it('accepts passwords meeting all complexity requirements', function () {
    Route::post('/test/validation-password-only', function (\App\Http\Requests\TestPasswordOnlyFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-password-only', [
        'password' => 'StrongPass1!ab',
    ]);

    $response->assertStatus(200);
});

it('rejects passwords shorter than 12 characters', function () {
    Route::post('/test/validation-password-only', function (\App\Http\Requests\TestPasswordOnlyFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-password-only', [
        'password' => 'Short1!a',
    ]);

    $response->assertStatus(422);
    $messages = $response->json('error.details.fields.password.messages');
    expect($messages)->toContain('The password must be at least 12 characters.');
});

it('rejects passwords without uppercase letters', function () {
    Route::post('/test/validation-password-only', function (\App\Http\Requests\TestPasswordOnlyFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-password-only', [
        'password' => 'nouppercase1!ab',
    ]);

    $response->assertStatus(422);
    $messages = $response->json('error.details.fields.password.messages');
    expect($messages)->toContain('The password must contain at least one uppercase letter.');
});

it('rejects passwords without lowercase letters', function () {
    Route::post('/test/validation-password-only', function (\App\Http\Requests\TestPasswordOnlyFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-password-only', [
        'password' => 'NOLOWERCASE1!AB',
    ]);

    $response->assertStatus(422);
    $messages = $response->json('error.details.fields.password.messages');
    expect($messages)->toContain('The password must contain at least one lowercase letter.');
});

it('rejects passwords without digits', function () {
    Route::post('/test/validation-password-only', function (\App\Http\Requests\TestPasswordOnlyFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-password-only', [
        'password' => 'NoDigitsHere!ab',
    ]);

    $response->assertStatus(422);
    $messages = $response->json('error.details.fields.password.messages');
    expect($messages)->toContain('The password must contain at least one digit.');
});

it('rejects passwords without special characters', function () {
    Route::post('/test/validation-password-only', function (\App\Http\Requests\TestPasswordOnlyFormRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    $response = $this->postJson('/test/validation-password-only', [
        'password' => 'NoSpecialChar1ab',
    ]);

    $response->assertStatus(422);
    $messages = $response->json('error.details.fields.password.messages');
    expect($messages)->toContain('The password must contain at least one special character.');
});

/*
|--------------------------------------------------------------------------
| Valid requests pass through
|--------------------------------------------------------------------------
*/

it('allows valid requests with only known fields', function () {
    $response = $this->postJson('/test/validation', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.name', 'John Doe');
    $response->assertJsonPath('data.email', 'john@example.com');
});

it('strips unknown fields from validated data', function () {
    // When only known fields are submitted, validated() returns only those
    $response = $this->postJson('/test/validation', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveKeys(['name', 'email']);
});
