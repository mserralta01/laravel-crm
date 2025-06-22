<?php

namespace Webkul\Tag\Repositories;

use App\Repositories\TenantAwareRepository;
class TagRepository extends TenantAwareRepository
{
    /**
     * Searchable fields
     */
    protected $fieldSearchable = [
        'name',
        'color',
        'user_id',
    ];

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Tag\Contracts\Tag';
    }
}
