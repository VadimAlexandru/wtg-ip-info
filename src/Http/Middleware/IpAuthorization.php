<?php

namespace wtg\IpCountryDetector\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use wtg\IpCountryDetector\Services\ErrorHandlerService;
use wtg\IpCountryDetector\Services\JWTService;

class IpAuthorization
{
    protected JWTService $jwtService;
    protected ErrorHandlerService $errorHandler;

    public function __construct(ErrorHandlerService $errorHandler)
    {
        $publicKeyPath = config('jwt.keys.public');
        $privateKeyPath = config('jwt.keys.private');

        $this->jwtService = new JWTService($publicKeyPath, $privateKeyPath);
        $this->errorHandler = $errorHandler;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $authEnabled = (bool) config('ipcountry.auth_enabled');
        $authKey = config('ipcountry.auth_key');

        if ($authEnabled && $this->isUnauthorized($request, $authKey)) {
            return new JsonResponse(['message' => 'Unauthorized'], 401);
        }

        if ($authEnabled) {
            try {
                $jwt = $this->extractTokenFromHeader($request);
                $this->jwtService->parseToken($jwt);
            } catch (Exception $e) {
                return $this->errorHandler->handle($e);
            }
        }

        return $next($request);
    }

    /**
     * Extract the JWT token from the Authorization header.
     *
     * @param Request $request
     * @return array|string|null
     * @throws Exception
     */
    protected function extractTokenFromHeader(Request $request): array|string|null
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new Exception('Token not provided');
        }

        return str_replace('Bearer ', '', $authHeader);
    }

    /**
     * Check if the request is unauthorized based on the custom IP key.
     *
     * @param Request $request
     * @param string $authKey
     * @return bool
     */
    protected function isUnauthorized(Request $request, string $authKey): bool
    {
        return $request->header('X-IPCountry-Key') !== $authKey;
    }
}