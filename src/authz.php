<?php

declare(strict_types=1);

/*
 * authz.php — authorization (RBAC) using the Symfony Security component.
 *
 * Permissions are decided by Voters through an AccessDecisionManager
 * (symfony/security-core). Roles live on the users table in the `role`
 * column: "user" (default) or "admin". Admin inherits every permission a
 * regular user has; regular users may only act on resources they own.
 *
 * Every check fails closed: any error, unknown attribute, or missing
 * subject grants nothing.
 */

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/** Minimal user object handed to the voter machinery. */
final class AuthzUser implements UserInterface {
    public function __construct(
        private readonly int $id,
        private readonly array $roles,
    ) {}

    public function getRoles(): array {
        return $this->roles;
    }

    public function getId(): int {
        return $this->id;
    }

    public function eraseCredentials(): void {}

    public function getPassword(): ?string {
        return null;
    }

    public function getUserIdentifier(): string {
        return (string)$this->id;
    }
}

/** Map a DB user row to ROLE_* tokens (unknown role ⇒ ROLE_USER). */
function authz_roles_for(?array $user): array {
    $role = is_array($user) && isset($user['role']) ? (string)$user['role'] : 'user';
    return strtoupper($role) === 'ADMIN' ? ['ROLE_ADMIN'] : ['ROLE_USER'];
}

/**
 * Decide whether the user may run $attribute on $subject.
 * Returns true only when a Voter explicitly grants the permission.
 */
function authz_can(int $userId, array $roles, string $attribute, mixed $subject = null): bool {
    static $manager = null;
    if ($manager === null) {
        $manager = new AccessDecisionManager([new VideoVoter()]);
    }
    try {
        $token = new UsernamePasswordToken(new AuthzUser($userId, $roles), 'main', $roles);
        return $manager->decide($token, [$attribute], $subject);
    } catch (Throwable $e) {
        return false;
    }
}

/** Video permissions. The subject is the video row (or its owner id). */
final class VideoVoter extends Voter {
    public const DELETE = 'video.delete';
    public const EDIT   = 'video.edit';

    protected function supports(string $attribute, mixed $subject): bool {
        return in_array($attribute, [self::DELETE, self::EDIT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
        $user = $token->getUser();
        if (!$user instanceof AuthzUser) {
            return false;
        }
        // Admin can do anything a regular user can.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $ownerId = 0;
        if (is_int($subject)) {
            $ownerId = $subject;
        } elseif (is_array($subject) && isset($subject['user_id'])) {
            $ownerId = (int)$subject['user_id'];
        }
        return $ownerId > 0 && $ownerId === $user->getId();
    }
}
