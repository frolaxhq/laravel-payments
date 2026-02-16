<?php

namespace Frolax\Payment\Services;

use Frolax\Payment\DTOs\CanonicalPayload;

class SchemaValidator
{
    protected array $requiredFields = [
        'order.id',
        'money.amount',
        'money.currency',
    ];

    protected array $gatewayRules = [];

    /**
     * Register validation rules for a specific gateway.
     */
    public function forGateway(string $gateway, array $rules): void
    {
        $this->gatewayRules[$gateway] = $rules;
    }

    /**
     * Validate a canonical payload against core + gateway-specific rules.
     */
    public function validate(array $data, ?string $gateway = null): array
    {
        $errors = [];

        // Core validation
        foreach ($this->requiredFields as $field) {
            if (!$this->hasNestedValue($data, $field)) {
                $errors[] = [
                    'field' => $field,
                    'rule' => 'required',
                    'message' => "The field [{$field}] is required.",
                ];
            }
        }

        // Amount must be positive
        if (isset($data['money']['amount']) && $data['money']['amount'] <= 0) {
            $errors[] = [
                'field' => 'money.amount',
                'rule' => 'positive',
                'message' => 'The field [money.amount] must be a positive number.',
            ];
        }

        // Currency must be 3 characters
        if (isset($data['money']['currency']) && strlen($data['money']['currency']) !== 3) {
            $errors[] = [
                'field' => 'money.currency',
                'rule' => 'currency_format',
                'message' => 'The field [money.currency] must be a 3-letter ISO currency code.',
            ];
        }

        // Gateway-specific rules
        if ($gateway && isset($this->gatewayRules[$gateway])) {
            foreach ($this->gatewayRules[$gateway] as $field => $rules) {
                if (in_array('required', (array) $rules) && !$this->hasNestedValue($data, $field)) {
                    $errors[] = [
                        'field' => $field,
                        'rule' => 'required',
                        'message' => "Gateway [{$gateway}] requires the field [{$field}].",
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * Check if data passes validation.
     */
    public function passes(array $data, ?string $gateway = null): bool
    {
        return empty($this->validate($data, $gateway));
    }

    protected function hasNestedValue(array $data, string $dotPath): bool
    {
        $keys = explode('.', $dotPath);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return $current !== null && $current !== '';
    }
}
