<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/global/php/functions.php");

api_controller_base_classes();
teams_rules();
data_validator();

class CreateTeamApiController extends ParameterizedApiController {
    public function post(): ApiResult {
        $body = JsonBodyReader::read();

        // validate body

        $validationResult = DataValidator::validate_associative_array($body, [
            "team_name" => (new DataValidatorRule())
                ->must_be_defined()
                ->must_be_string()
                ->string_must_have_length(TEAM_NAME_MIN_LENGTH, TEAM_NAME_MAX_LENGTH)
        ]);

        if (!$validationResult->isSuccess())
        {
            return new BadArgumentsApiResult([
                "error_code" => "BODY_NOT_VALIDATED", 
                "error" => [
                    "invalid_rule_code" => $validationResult->getInvalidRuleCode(),
                    "invalid_rule_details" => $validationResult->getInvalidRuleDetails()
                ]   
            ]);
        }

        // TODO: create team

        return new OkApiResult(new stdClass);
    }
}

ApiRouter::on("/api/v1/teams/create", CreateTeamApiController::class);