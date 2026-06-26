<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class GoogleAuthController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function redirect(Request $request): JsonResponse
    {
        $returnUrl = $request->query('returnUrl');
        $state = base64_encode(json_encode(['returnUrl' => $returnUrl]));
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('google');

        return response()->json([
            'url' => $provider
                ->stateless()
                ->with(['state' => $state])
                ->redirect()
                ->getTargetUrl(),
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->away($this->buildRedirectUrl(null, 'Google authentication was canceled or failed.', $request->query('state')));
        }

        try {
            /** @var AbstractProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->away($this->buildRedirectUrl(null, 'Unable to authenticate with Google.', $request->query('state')));
        }

        $result = $this->authService->loginWithGoogle([
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? $googleUser->getEmail(),
            'email' => $googleUser->getEmail(),
            'avatar' => $googleUser->getAvatar(),
        ]);

        return redirect()->away($this->buildRedirectUrl($result['token'], null, $request->query('state')));
    }

    private function buildRedirectUrl(?string $token, ?string $error = null, ?string $state = null): string
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $queryParams = [];

        if ($token) {
            $queryParams['googleToken'] = $token;
        }

        if ($error) {
            $queryParams['googleError'] = $error;
        }

        if ($state) {
            $payload = json_decode(base64_decode($state), true);
            if (is_array($payload) && ! empty($payload['returnUrl'])) {
                $queryParams['returnUrl'] = $payload['returnUrl'];
            }
        }

        $queryString = http_build_query($queryParams);
        $redirectPath = '/admin/login';

        return $queryString ? "{$frontendUrl}{$redirectPath}?{$queryString}" : "{$frontendUrl}{$redirectPath}";
    }
}
