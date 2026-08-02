<?php

namespace App\Services;

use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;
use Lcobucci\JWT\UnencryptedToken;

/**
 * Thin wrapper over the Firebase Admin SDK (kreait/firebase-php).
 *
 * Centralizes every server-side Firebase interaction so controllers/middleware
 * never touch the SDK directly. Bound as a singleton in FirebaseServiceProvider.
 */
class FirebaseService
{
    private FirebaseAuth $auth;

    public function __construct()
    {
        $credentials = base_path(config('firebase.credentials'));


        $factory = (new Factory())->withServiceAccount($credentials);
        $this->auth = $factory->createAuth();
    }

    /**
     * Verify a Firebase ID token. Returns the decoded token on success.
     * Throws FailedToVerifyToken on any problem (expired, bad signature, etc.).
     */
    public function verifyIdToken(string $idToken): UnencryptedToken
    {
        return $this->auth->verifyIdToken($idToken);
    }

    /** Extract the Firebase UID from a verified token. */
    public function uidFromToken(UnencryptedToken $token): string
    {
        return $token->claims()->get('sub');
    }

    /** Create a Firebase user (email/password). Returns the new UID. */
    public function createUser(string $email, string $password, ?string $displayName = null): string
    {
        $properties = [
            'email'    => $email,
            'password' => $password,
        ];
        if ($displayName) {
            $properties['displayName'] = $displayName;
        }

        $user = $this->auth->createUser($properties);

        return $user->uid;
    }

    /** Update a Firebase user's email / password / display name. */
    public function updateUser(string $uid, array $properties): void
    {
        $this->auth->updateUser($uid, $properties);
    }

    /** Disable or enable a Firebase user (blocks/allows sign-in). */
    public function setDisabled(string $uid, bool $disabled): void
    {
        $this->auth->updateUser($uid, ['disabled' => $disabled]);
    }

    /** Permanently delete a Firebase user. */
    public function deleteUser(string $uid): void
    {
        $this->auth->deleteUser($uid);
    }

    /** Send a password-reset link (returns the link if you prefer to email it yourself). */
    public function passwordResetLink(string $email): string
    {
        return $this->auth->getPasswordResetLink($email);
    }
}
