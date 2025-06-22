<?php

namespace Webkul\Activity\Repositories;

use App\Repositories\TenantAwareRepository;
class ParticipantRepository extends TenantAwareRepository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Activity\Contracts\Participant';
    }
}
