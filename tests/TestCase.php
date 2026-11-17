<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * For tests of billing / payroll rules written before owner approvals (v2.14.0): moves the approval start far
     * into the future so they keep testing what they were written for. ApprovalTest covers the approvals themselves.
     */
    protected function withoutOwnerApproval(): void
    {
        config(['approvals.start' => '2100-01-01', 'approvals.staff' => false]);
    }
}
