<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    /**
     * Mark the authenticated demo account as verified using a signed link.
     */
    public function verify(Request $request, int $id, string $hash): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless((int) $user->getKey() === $id, 403);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Resend the verification email from a plain form post.
     */
    public function send(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
