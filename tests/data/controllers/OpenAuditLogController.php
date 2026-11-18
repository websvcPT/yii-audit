<?php

namespace websvc\audit\tests\data\controllers;

use websvc\audit\controllers\AuditLogController;

/**
 * Audit log controller open to everyone and rendered without a layout, for tests.
 */
class OpenAuditLogController extends AuditLogController
{
    public $layout = false;

    public function behaviors(): array
    {
        return [];
    }
}
