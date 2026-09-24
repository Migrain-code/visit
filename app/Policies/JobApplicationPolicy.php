<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

/**
 * İş başvuruları: iletişim talepleriyle aynı yetkiyle görülür ve işlenir.
 */
class JobApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesRequests();
    }

    public function view(User $user, JobApplication $application): bool
    {
        return $user->managesRequests();
    }

    /** Başvurular siteden gelir; panelden elle oluşturulmaz. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, JobApplication $application): bool
    {
        return $user->managesRequests();
    }

    public function delete(User $user, JobApplication $application): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
