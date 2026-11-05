<?php

namespace app\core\interfaces;

interface AccessObjectEntity
{
    const IDENT = 'id';

    const OBJECT_ROLE = 'role';
    const OBJECT_USER_GROUP = 'user_group';
    const OBJECT_USER = 'user';
    const OBJECT_PERSON = 'person';

    const OBJECTS = [
        self::OBJECT_ROLE,
        self::OBJECT_USER_GROUP,
        self::OBJECT_USER,
        self::OBJECT_PERSON,
    ];

    const ROUTE_ACCESS_OBJECTS = [
        self::OBJECT_ROLE,
        self::OBJECT_USER_GROUP,
        self::OBJECT_USER,
    ];

    public function getId();
    public function getName(): string;
    public function isActive(): bool;
    public function isBlocked(): bool;
    public function toArray();
}