<?php

class DataValidatorRule {
    private array $rules;

    public function __construct() {
        $this->rules = array();
    }

    private function add_function_rule(string $rule_code, \Closure $closure, ?array $details = null) {
        array_push($this->rules, [$closure, $rule_code, $details]);
    }

    public function must_be_int(): DataValidatorRule {
        $this->add_function_rule("MUST_BE_INT", function($v) { return is_int($v); });

        return $this;
    }

    public function must_be_string(): DataValidatorRule {
        $this->add_function_rule("MUST_BE_STRING", function($v) { return is_string($v); });

        return $this;
    }

    public function string_must_have_length($minLength, $maxLength): DataValidatorRule {
        $this->add_function_rule(
            "STRING_MUST_HAVE_LENGTH", 
            function($v) use ($minLength, $maxLength) { 
                $len = strlen($v);
                return $minLength <= $len && $len <= $maxLength; 
            }, 
            [ "min_length" => $minLength, "max_length" => $maxLength ]
        );

        return $this;
    }

    public function must_be_defined(): DataValidatorRule {
        $this->add_function_rule("MUST_BE_DEFINED", function($v) { 
            return isset($v);
        });

        return $this;
    }

    public function validate($v): DataValidationResult {
        foreach ($this->rules as $rule) {
            if (!$rule[0]($v))
                return new DataValidationResult(false, $rule[1], $rule[2]);
        }

        return new DataValidationResult(true);;
    }
}

class DataValidationResult {
    private bool $success;
    private ?string $invalid_rule_code;
    private ?array $invalid_rule_code_details;

    public function __construct(
        bool $success, 
        ?string $invalid_rule_code = null, 
        ?array $invalid_rule_code_details = null) 
    {
        $this->success = $success;
        $this->invalid_rule_code = $invalid_rule_code;
        $this->invalid_rule_code_details = $invalid_rule_code_details;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getInvalidRuleCode(): ?string {
        return $this->invalid_rule_code;
    }

    public function getInvalidRuleDetails(): ?array {
        return $this->invalid_rule_code_details;
    }
}

class DataValidator {
    public static function validate_associative_array(array $array, array $rules): DataValidationResult {
        foreach ($rules as $key => $rule) {
            $validationResult = $rule->validate($array[$key]);
            
            if (!$validationResult->isSuccess())
                return $validationResult;
        }

        return new DataValidationResult(true);
    }
}