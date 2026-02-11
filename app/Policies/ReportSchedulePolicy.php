<?php

namespace App\Policies;

use App\Models\ReportSchedule;
use App\Models\User;

class ReportSchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReportSchedule $reportSchedule): bool
    {
        return $user->id === $reportSchedule->user_id || $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReportSchedule $reportSchedule): bool
    {
        return $user->id === $reportSchedule->user_id || $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReportSchedule $reportSchedule): bool
    {
        return $user->id === $reportSchedule->user_id || $user->is_admin;
    }
}
