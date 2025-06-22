<?php

namespace Webkul\EmailTemplate\Repositories;

use App\Repositories\TenantAwareRepository;
class EmailTemplateRepository extends TenantAwareRepository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\EmailTemplate\Contracts\EmailTemplate';
    }
}
