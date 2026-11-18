<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * For tests of billing / payroll rules written before owner approvals (v2.14.0): moves the approval start far
     * into the future so they keep testing what they were written for. ApprovalTest covers the approvals themselves.
     */
    /** The app's developer (owner, 19 Nov 2026): Super Admin who also keeps Users, Activity log and imports. */
    protected function makeDeveloper(\App\Models\User $user): \App\Models\User
    {
        $user->forceFill(['role' => 'admin', 'is_super_admin' => true, 'is_developer' => true])->save();

        return $user;
    }

    protected function withoutOwnerApproval(): void
    {
        config(['approvals.start' => '2100-01-01', 'approvals.staff' => false]);
    }
}
