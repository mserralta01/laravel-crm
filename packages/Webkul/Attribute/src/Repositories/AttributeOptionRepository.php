<?php

namespace Webkul\Attribute\Repositories;

use App\Repositories\TenantAwareRepository;
class AttributeOptionRepository extends TenantAwareRepository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Attribute\Contracts\AttributeOption';
    }
}
