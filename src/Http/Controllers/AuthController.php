<?php

namespace UoMosul\UomIdPackageLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Ory\Kratos\Client\Api\FrontendApi;

class AuthController
{
    /**
     * Get Current User from Ory Kratos
     */
    public function index(Request $request)
    {
        // TODO: Move configuration logic somewhere else?
        $config = new \Ory\Kratos\Client\Configuration;
        $config->setHost(config('uom-id.auth.uom.routes.host'));

        $frontendApi = new FrontendApi(null, $config);

        try {
            // Get current user session
            $session = $frontendApi->toSession(null, $request->header('Cookie'));
            $identity = $session->getIdentity()->getTraits();

            $user = ['id' => $session->getIdentity()->getId(), 'name' => $identity->name, 'email' => $identity->email];

            return (object) $user;
        } catch (\Ory\Kratos\Client\ApiException $err) {
            // Not authenticated
            if ($err->getCode() !== 401) {
                throw $err;
            }

            return null;
        }
    }

    public function current_user(Request $request)
    {
        return $request->user() ?? (object) [];
    }

    public function login(Request $request, FrontendApi $frontendApi)
    {
        try {
            $frontendApi->createBrowserLoginFlow(null, null, route(config('uom-id.auth.uom.redirects.login')), $request->header('Cookie'));

            $loginUrl = $request::create(config('uom-id.auth.uom.routes.login'))->fullUrlWithQuery([
                'return_to' => route(config('uom-id.auth.uom.redirects.login')),
            ]);

            return redirect($loginUrl);
        } catch (\Ory\Kratos\Client\ApiException $err) {
            $errorBody = json_decode($err->getResponseBody());

            // Handle null error body
            if (is_null($errorBody)) {
                if (App::hasDebugModeEnabled()) {
                    return response()->json(['message' => $err->getMessage()]);
                } else {
                    return $this->genericJsonErrorResponse();
                }
            }

            $errorId = $errorBody->error->id;

            // TODO: Complete handling all errorId cases
            switch ($errorId) {
                case 'session_already_available':
                    return redirect(route(config('uom-id.auth.uom.redirects.login')));
                default:
                    return $this->genericJsonErrorResponse();
            }
        }
    }

    public function logout(Request $request, FrontendApi $frontendApi)
    {
        try {
            $response = $frontendApi->createBrowserLogoutFlow($request->header('Cookie'));
            $logoutUrl = $request::create($response['logoutUrl'])->fullUrlWithQuery([
                'return_to' => route(config('uom-id.auth.uom.redirects.logout')),
            ]);

            return redirect($logoutUrl);
        } catch (\Ory\Kratos\Client\ApiException $err) {
            $errorBody = json_decode($err->getResponseBody());

            // Handle null error body
            if (is_null($errorBody)) {
                if (App::hasDebugModeEnabled()) {
                    return response()->json(['message' => $err->getMessage()]);
                } else {
                    return $this->genericJsonErrorResponse();
                }
            }

            // TODO: Use errorId as you wish
            $errorId = $errorBody->error->id;

            return redirect(route(config('uom-id.auth.uom.redirects.logout')));
        }
    }

    private function genericJsonErrorResponse()
    {
        return response()->json(['message' => 'An unknown error has occurred, please contact the server administrator.']);
    }
}