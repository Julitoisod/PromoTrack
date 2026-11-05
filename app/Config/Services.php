<?php

namespace Config;

use App\Libraries\DateDurationCalculator;
use App\Libraries\WorkflowStateMachine;
use App\Models\AssessmentRequirementModel;
use App\Models\DocumentRequirementModel;
use App\Models\DocumentRequirementTemplateModel;
use App\Models\DocumentVersionModel;
use App\Models\AuditLogModel;
use App\Models\EndorsementModel;
use App\Models\NotificationModel;
use App\Models\PackageDocumentModel;
use App\Models\PackageStatusHistoryModel;
use App\Models\PhysicalFitnessTestModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionOrderModel;
use App\Models\PromotionPackageModel;
use App\Models\PromotionRuleModel;
use App\Models\PromotionRuleVersionModel;
use App\Models\ReservistModel;
use App\Models\ReservistTrainingModel;
use App\Models\TrainingTypeModel;
use App\Models\UserModel;
use App\Services\AuditLogService;
use App\Services\DocumentValidationService;
use App\Services\EligibilityService;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\PackageWorkflowService;
use App\Services\ReportService;
use App\Services\ReservistRegistrationService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    public static function dateDurationCalculator(bool $getShared = true): DateDurationCalculator
    {
        if ($getShared) {
            return static::getSharedInstance('dateDurationCalculator');
        }

        return new DateDurationCalculator();
    }

    public static function eligibilityService(bool $getShared = true): EligibilityService
    {
        if ($getShared) {
            return static::getSharedInstance('eligibilityService');
        }

        return new EligibilityService(
            new ReservistModel(),
            new PromotionRuleModel(),
            new PromotionRuleVersionModel(),
            new ReservistTrainingModel(),
            new TrainingTypeModel(),
            new PhysicalFitnessTestModel(),
            static::dateDurationCalculator(),
            new PromotionAssessmentModel(),
            new AssessmentRequirementModel(),
        );
    }

    public static function documentValidationService(bool $getShared = true): DocumentValidationService
    {
        if ($getShared) {
            return static::getSharedInstance('documentValidationService');
        }

        return new DocumentValidationService(
            new DocumentRequirementTemplateModel(),
            new DocumentRequirementModel(),
            new PackageDocumentModel(),
        );
    }

    public static function fileStorageService(bool $getShared = true): FileStorageService
    {
        if ($getShared) {
            return static::getSharedInstance('fileStorageService');
        }

        return new FileStorageService(new DocumentVersionModel());
    }

    public static function packageWorkflowService(bool $getShared = true): PackageWorkflowService
    {
        if ($getShared) {
            return static::getSharedInstance('packageWorkflowService');
        }

        return new PackageWorkflowService(
            new PromotionPackageModel(),
            new PackageStatusHistoryModel(),
            static::documentValidationService(),
            new WorkflowStateMachine(),
            static::auditLogService(),
        );
    }

    public static function auditLogService(bool $getShared = true): AuditLogService
    {
        if ($getShared) {
            return static::getSharedInstance('auditLogService');
        }

        return new AuditLogService(new AuditLogModel());
    }

    public static function notificationService(bool $getShared = true): NotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('notificationService');
        }

        return new NotificationService(new NotificationModel());
    }

    public static function reservistRegistrationService(bool $getShared = true): ReservistRegistrationService
    {
        if ($getShared) {
            return static::getSharedInstance('reservistRegistrationService');
        }

        return new ReservistRegistrationService(
            new ReservistModel(),
            new PromotionRuleModel(),
            new UserModel(),
        );
    }

    public static function reportService(bool $getShared = true): ReportService
    {
        if ($getShared) {
            return static::getSharedInstance('reportService');
        }

        return new ReportService(
            new PromotionOrderModel(),
            new EndorsementModel(),
            new PromotionAssessmentModel(),
        );
    }
}
