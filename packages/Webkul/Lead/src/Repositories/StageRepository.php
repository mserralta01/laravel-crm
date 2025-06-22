<?php

namespace Webkul\Lead\Repositories;

use App\Repositories\TenantAwareRepository;
class StageRepository extends TenantAwareRepository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Lead\Contracts\Stage';
    }
}
