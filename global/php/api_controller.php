<?php

class ApiResult {
    private int $statusCode;
    private mixed $value;
    private bool $doNotEncode;

    /**
     * @param int $statusCode 
     * @param string $contentType 
     * @param mixed $content 
     */
    public function __construct(mixed $value, int $statusCode, bool $doNotEncode = false) {
    	$this->statusCode = $statusCode;
    	$this->value = $value;
        $this->doNotEncode = $doNotEncode;
    }

	/**
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @return mixed
	 */
	public function getValue(): mixed {
		return $this->value;
	}

	/**
	 * @return bool
	 */
	public function getDoNotEncode(): bool {
		return $this->doNotEncode;
	}
}

class EmptyContent {}

class OkApiResult extends ApiResult {
    public function __construct(mixed $value = "Ok", $doNotEncode = false) {
        parent::__construct($value, 200, $doNotEncode);
    }
}

class NotImplementedApiResult extends ApiResult {
    /**
     * @param int $statusCode 
     * @param string $contentType 
     * @param mixed $content 
     */
    public function __construct() {
        parent::__construct(new EmptyContent, 501);
    }
}

class BadArgumentsApiResult extends ApiResult {
    public function __construct(mixed $error = "Bad request") {
        parent::__construct($error, 400);
    }
}

class ResourceNotFoundApiResult extends ApiResult {
    public function __construct(mixed $error = "Resource not found") {
        parent::__construct($error, 404);
    }
}

class UnknownErrorResult extends ApiResult {
    public function __construct(mixed $error = "Unknown Error") {
        parent::__construct($error, 500);
    }
}

class UnauthorizedResult extends ApiResult {
    public function __construct(mixed $error = "Unauthorized") {
        parent::__construct($error, 401);
    }
}

class ApiController {
    public function get(): ApiResult { 
        return new NotImplementedApiResult; 
    }

    public function post(): ApiResult { 
        return new NotImplementedApiResult; 
    }

    public function put(): ApiResult { 
        return new NotImplementedApiResult; 
    }

    public function delete(): ApiResult { 
        return new NotImplementedApiResult; 
    }
}

class ParameterizedApiController extends ApiController {
    private array $parameters;
    
    public function __construct(array $parameters) {
        $this->parameters = $parameters;
    }

    protected function getParameter(mixed $key): mixed {
        return $this->parameters[$key];
    }
}

class JsonBodyReader {
    public static function read(): array {
        $result = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new Exception("could not decode json, code: " . json_last_error());

        return $result;
    }
}

interface ApiResultSerializer {
    function serialize(ApiResult $result): string;
    function getContentType(): string;
}

class JsonApiResultSerializer implements ApiResultSerializer {
    public function serialize(ApiResult $result): string {
        if ($result->getDoNotEncode())
            return $result->getValue();

        return json_encode($result->getValue());
    }
    public function getContentType(): string {
        return "application/json";
    }
}

class ApiControllerExecutor {
    public static function execute(mixed $controller, ApiResultSerializer $serializer) {
        try {
            $method = $_SERVER['REQUEST_METHOD'];

            switch ($method) {
                case 'POST':
                    $result = $controller->post();
                    break;
                case 'GET':
                    $result = $controller->get();
                    break;
                case 'PUT':
                    $result = $controller->put();
                    break;
                case 'DELETE':
                    $result = $controller->delete();
                    break;
                default:
                    $result = new NotImplementedApiResult;
                    break;
            }
        } catch (InvalidParameterException $exception) {
            $result = new BadArgumentsApiResult($exception->getMessage());
        } catch (InvalidOperationException $exception) {
            $result = new BadArgumentsApiResult($exception->getMessage());
        } catch (ResourceNotFoundException $exception) {
            $result = new ResourceNotFoundApiResult($exception->getMessage());
        } catch (Exception $exception) {
            error_log($exception->getMessage());
            Logging::PutLog("Got exception in " . $controller::class . ":" . $exception->getMessage());
            $result = new UnknownErrorResult(["message" => $exception->getMessage()]);
        }

        http_response_code($result->getStatusCode());
        header("Content-Type: " . $serializer->getContentType());
        echo $serializer->serialize($result);
    }
}

class ApiRouter {
    private static ?ApiResultSerializer $defaultSerializer;

    public static function getSerializer(): ApiResultSerializer {
        if (!isset(self::$defaultSerializer))
            self::$defaultSerializer = new JsonApiResultSerializer;
        
        return self::$defaultSerializer;
    }
    
    public static function setSerializer(ApiResultSerializer $serializer): void {
        self::$defaultSerializer = $serializer;
    }

    public static function on(string $regex, string $controller_class_name): void  {
        $params = $_SERVER['REQUEST_URI'];
        $params = (stripos($params, "/") !== 0) ? "/" . $params : $params;
        $regex = str_replace('/', '\/', $regex);

        $is_match = preg_match('/^' . ($regex) . '$/', $params, $matches, PREG_OFFSET_CAPTURE);

        if ($is_match) {
            // first value is normally the route, lets remove it
            array_shift($matches);
            // Get the matches as parameters
            $params = array_map(function ($param) {
                return $param[0];
            }, $matches);

            $ref = new ReflectionClass($controller_class_name);
            $instance = $ref->newInstanceArgs(array($params));

            ApiControllerExecutor::execute($instance, self::getSerializer());
        }
    }
}