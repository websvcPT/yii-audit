<?php

namespace websvc\audit\tests\data\controllers;

use websvc\audit\commands\AuditController;

/**
 * Console audit controller that keeps its output in memory, for tests.
 */
class QuietAuditController extends AuditController
{
    public string $output = '';

    public function stdout($string)
    {
        $this->output .= $string;

        return strlen($string);
    }

    public function stderr($string)
    {
        $this->output .= $string;

        return strlen($string);
    }
}
