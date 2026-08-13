<?php

declare(strict_types=1);

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/*
 * validation.php — request data validation for the videohub API.
 *
 * Uses the Symfony Validator component (symfony/validator). Each endpoint
 * declares a set of field constraints with Vietnamese messages; the helpers
 * here turn constraint violations into a flat list of error strings.
 *
 * Note: Symfony Validator 8.x only accepts named arguments in constraints.
 */

/**
 * Validate an associative array against a set of field constraints.
 *
 * `$constraints` maps a field name to one or more Assert constraints (the
 * field is required). Unknown extra fields are allowed. Returns a list of
 * Vietnamese error messages, first violation wins per field; empty when valid.
 */
function validate_payload(array $data, array $constraints): array {
    $violations = Validation::createValidator()->validate(
        $data,
        new Assert\Collection(
            fields: $constraints,
            allowExtraFields: true,
            allowMissingFields: false,
            missingFieldsMessage: 'Thiếu trường dữ liệu: {{ field }}.',
        )
    );

    $errors = [];
    foreach ($violations as $violation) {
        $field = trim((string)$violation->getPropertyPath(), '[]');
        if ($field === '' || isset($errors[$field])) {
            continue;
        }
        $errors[$field] = (string)$violation->getMessage();
    }
    return $errors;
}

/** Shared string checks: value must be a non-blank string. */
function not_blank(string $message): array {
    return [
        new Assert\Type(type: 'string', message: $message),
        new Assert\NotBlank(message: $message),
    ];
}

/** Constraints for POST /api/register. */
function register_constraints(): array {
    return [
        'username' => [
            new Assert\Type(type: 'string', message: 'Username phải gồm 3–32 ký tự chữ, số hoặc gạch dưới.'),
            new Assert\Regex(
                pattern: '/^[A-Za-z0-9_]{3,32}$/',
                message: 'Username phải gồm 3–32 ký tự chữ, số hoặc gạch dưới.',
            ),
        ],
        'email' => [
            new Assert\Type(type: 'string', message: 'Email phải là tài khoản Gmail hợp lệ (…@gmail.com).'),
            new Assert\Regex(
                pattern: '/^[^@\s]+@gmail\.com$/i',
                message: 'Email phải là tài khoản Gmail hợp lệ (…@gmail.com).',
            ),
        ],
        'password' => [
            ...not_blank('Password phải có ít nhất 6 ký tự.'),
            new Assert\Length(min: 6, minMessage: 'Password phải có ít nhất 6 ký tự.'),
        ],
    ];
}

/** Constraints for POST /api/login. */
function login_constraints(): array {
    return [
        'username' => not_blank('Thiếu Gmail/username hoặc password.'),
        'password' => [
            ...not_blank('Thiếu Gmail/username hoặc password.'),
            new Assert\Length(min: 6, minMessage: 'Password phải có ít nhất 6 ký tự.'),
        ],
    ];
}
