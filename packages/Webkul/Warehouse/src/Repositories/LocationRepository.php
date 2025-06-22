<?php

namespace Webkul\Warehouse\Repositories;

use App\Repositories\TenantAwareRepository;
class LocationRepository extends TenantAwareRepository
{
    /**
     * Searchable fields
     */
    protected $fieldSearchable = [
        'name',
        'warehouse_id',
    ];

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Warehouse\Contracts\Location';
    }
}
