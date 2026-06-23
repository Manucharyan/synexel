<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookPageController extends Controller
{
    private function ensureApiToken(Request $request): void
    {
        if ($request->session()->has('api_token')) {
            return;
        }

        $token = $request->user()->createToken('web-ui')->plainTextToken;
        $request->session()->put('api_token', $token);
    }

    public function index(Request $request): View
    {
        $this->ensureApiToken($request);

        return view('webhooks.index');
    }
}
