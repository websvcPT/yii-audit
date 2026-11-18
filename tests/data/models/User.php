<?php

namespace websvc\audit\tests\data\models;

use yii\base\BaseObject;
use yii\web\IdentityInterface;

/**
 * In-memory identity for tests.
 */
class User extends BaseObject implements IdentityInterface
{
    public int|string|null $id = null;
    public ?string $username = null;

    public static function findIdentity($id): ?static
    {
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null): ?static
    {
        return null;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getAuthKey(): ?string
    {
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        return false;
    }
}
