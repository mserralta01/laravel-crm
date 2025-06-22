<?php

namespace Webkul\User\Repositories;

use App\Repositories\TenantAwareRepository;
class GroupRepository extends TenantAwareRepository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\User\Contracts\Group';
    }
}
