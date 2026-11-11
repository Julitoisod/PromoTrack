<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

/**
 * PromoTrack AFP-RF custom Shield groups, permissions, and matrix.
 *
 * Groups and permissions defined here are the single source of truth for
 * authorization. Every protected controller method MUST additionally verify
 * unit-level and record-level ownership on top of this permission check
 * (see AccessControlService).
 */
class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * Self-registration creates a reservist, never a staff account: a
     * reservist signs up to upload their own promotion documents, and
     * every staff role is still assigned by an administrator.
     *
     * This used to be `unit_s1_clerk`, which meant anyone who signed up
     * on the public form received a clerk's `reservists.create` /
     * `reservists.update` rights over their whole unit.
     */
    public string $defaultGroup = 'reservist';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     *
     * @var array<string, array<string, string>>
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Administrator',
            'description' => 'Complete, unrestricted control of PromoTrack AFP-RF.',
        ],
        'system_administrator' => [
            'title'       => 'System Administrator',
            'description' => 'Manages users, organizational units, promotion rules, settings, and backups.',
        ],
        'unit_s1_clerk' => [
            'title'       => 'Unit S1 Clerk',
            'description' => 'Creates reservist profiles, runs eligibility assessments, and assembles promotion packages within their own unit.',
        ],
        'cdc_administrator' => [
            'title'       => 'CDC Administrator',
            'description' => 'Validates promotion packages and documents at the Career Development Center stage.',
        ],
        'rcdg_reviewer' => [
            'title'       => 'RCDG Reviewer',
            'description' => 'Reviews and endorses promotion packages at the RCDG stage.',
        ],
        'commander_viewer' => [
            'title'       => 'Commander / Viewer',
            'description' => 'Read-only access to dashboards and reports.',
        ],
        'records_officer' => [
            'title'       => 'Records Officer',
            'description' => 'Records external forwarding, external decisions, and promotion orders.',
        ],
        'reservist' => [
            'title'       => 'Reservist',
            'description' => 'Views their own promotion record and uploads their own promotion documents. The group every self-registered account joins.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'users.manage'              => 'Create, update, deactivate, and assign roles to users',
        'units.manage'               => 'Create and update organizational units',
        'rules.manage'                => 'Create and update versioned promotion rules',
        'reservists.create'           => 'Create new reservist profiles',
        'reservists.view'              => 'View reservist profiles',
        'reservists.update'            => 'Update reservist profiles',
        'promotions.view_own'          => 'View your own promotion record and document checklist',
        'assessments.compute'          => 'Run eligibility assessments against active promotion rules',
        'assessments.verify'           => 'Verify/countersign a computed eligibility assessment',
        'packages.create'              => 'Create a promotion package for a reservist',
        'packages.submit_to_cdc'       => 'Submit a promotion package to the CDC',
        'packages.validate_at_cdc'     => 'Validate a promotion package at the CDC stage',
        'packages.submit_to_rcdg'      => 'Submit a validated package to the RCDG',
        'packages.review_at_rcdg'      => 'Review a promotion package at the RCDG stage',
        'packages.return'              => 'Return a promotion package to a prior stage with remarks',
        'packages.endorse'             => 'Endorse a promotion package at the RCDG stage',
        'documents.upload'             => 'Upload a promotion-package document',
        'documents.verify'             => 'Verify an uploaded promotion-package document',
        'documents.reject'             => 'Reject an uploaded promotion-package document',
        'waivers.approve'              => 'Approve or deny a document waiver request',
        'forwarding.record'            => 'Record forwarding of a package to an external approving authority',
        'external_decisions.record'    => 'Record a decision received from an external approving authority',
        'promotion_orders.record'      => 'Record an official promotion order',
        'reports.view'                 => 'View generated reports',
        'reports.generate'             => 'Generate PDF/Excel reports',
        'audit_logs.view'              => 'View system audit logs',
        'settings.manage'              => 'Manage system settings',
        'backups.manage'               => 'Create and manage database backups',
        'companies.manage'             => 'Add and edit the companies offered under each CDC at sign-up',
        'enrollment.manage'            => 'View student enrollment and reservist classification, verify CORs, assign Standby / Ready Reserve',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups. Wildcards follow the pattern `prefix.*`
     * and match any permission beginning with `prefix.`.
     */
    public array $matrix = [
        'superadmin' => [
            'users.*',
            'units.*',
            'rules.*',
            'reservists.*',
            'promotions.*',
            'assessments.*',
            'packages.*',
            'documents.*',
            'waivers.*',
            'forwarding.*',
            'external_decisions.*',
            'promotion_orders.*',
            'reports.*',
            'audit_logs.*',
            'settings.*',
            'backups.*',
            'companies.*',
            'enrollment.*',
        ],
        'system_administrator' => [
            'users.manage',
            'units.manage',
            'companies.manage',
            'enrollment.manage',
            'rules.manage',
            'reservists.view',
            'reports.view',
            'reports.generate',
            'audit_logs.view',
            'settings.manage',
            'backups.manage',
        ],
        // The S1 clerk verifies the documents the reservist uploaded before
        // the package leaves the unit -- the uploader and the verifier are
        // still two different people, which is the point of the gate.
        'unit_s1_clerk' => [
            'reservists.create',
            'reservists.view',
            'reservists.update',
            'assessments.compute',
            'packages.create',
            'packages.submit_to_cdc',
            'documents.upload',
            'documents.verify',
            'documents.reject',
            'reports.view',
            'reports.generate',
        ],
        // companies.manage / enrollment.manage: the client asked that the CDC
        // side, not only an administrator, can maintain the sign-up companies
        // and work the admin panel's enrollment and classification folders.
        'cdc_administrator' => [
            'reservists.view',
            'companies.manage',
            'enrollment.manage',
            'assessments.verify',
            'packages.validate_at_cdc',
            'packages.submit_to_rcdg',
            'packages.return',
            'documents.verify',
            'documents.reject',
            'waivers.approve',
            'reports.view',
            'reports.generate',
        ],
        'rcdg_reviewer' => [
            'reservists.view',
            'packages.review_at_rcdg',
            'packages.return',
            'packages.endorse',
            'documents.verify',
            'documents.reject',
            'waivers.approve',
            'reports.view',
            'reports.generate',
        ],
        'commander_viewer' => [
            'reservists.view',
            'reports.view',
            'reports.generate',
        ],
        'records_officer' => [
            'reservists.view',
            'forwarding.record',
            'external_decisions.record',
            'promotion_orders.record',
            'reports.view',
            'reports.generate',
        ],
        // Deliberately NOT `reservists.view`, which is the whole-roster
        // listing. A reservist reaches only their own record, through the
        // `/my` routes, and record ownership on top of this permission is
        // enforced by `BaseController::canAccessPackageRow()`.
        'reservist' => [
            'promotions.view_own',
            'documents.upload',
        ],
    ];
}
