<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JobPolicy
{
    public function view(User $user, Job $job): Response
    {
        return $this->owns($user, $job);
    }

    public function update(User $user, Job $job): Response
    {
        return $this->owns($user, $job);
    }

    public function delete(User $user, Job $job): Response
    {
        return $this->owns($user, $job);
    }

    /**
     * Bukan pemilik = 404, bukan 403, supaya keberadaan loker orang lain tidak terbocor.
     */
    private function owns(User $user, Job $job): Response
    {
        return (int) $user->id === (int) $job->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}