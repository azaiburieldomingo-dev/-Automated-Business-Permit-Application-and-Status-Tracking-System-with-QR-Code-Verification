<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Statuses in which an applicant may still edit/submit their application.
     *
     * @var array<int, string>
     */
    private const EDITABLE_STATUSES = [
        Application::STATUS_DRAFT,
        Application::STATUS_FOR_CORRECTION,
    ];

    public function view(User $user, Application $application): bool
    {
        return $this->ownsApplication($user, $application) || $user->isBplo() || $user->isAdmin();
    }

    public function update(User $user, Application $application): bool
    {
        return $this->ownsApplication($user, $application) && $this->isEditable($application);
    }

    public function submit(User $user, Application $application): bool
    {
        return $this->ownsApplication($user, $application) && $this->isEditable($application);
    }

    public function review(User $user): bool
    {
        return $user->isBplo() || $user->isAdmin();
    }

    private function ownsApplication(User $user, Application $application): bool
    {
        return $user->getKey() === $application->user_id;
    }

    private function isEditable(Application $application): bool
    {
        return in_array($application->status, self::EDITABLE_STATUSES, true);
    }
}
