<?php

namespace Webkul\Automation\Repositories;

use App\Repositories\TenantAwareRepository;
use Webkul\Automation\Contracts\Workflow;
class WorkflowRepository extends TenantAwareRepository
{
    /**
     * Specify Model class name.
     */
    public function model(): string
    {
        return Workflow::class;
    }
}
