<?php

namespace Webkul\Activity\Repositories;

use App\Repositories\TenantAwareRepository;
class FileRepository extends TenantAwareRepository
{
    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return \Webkul\Activity\Contracts\File::class;
    }
}
