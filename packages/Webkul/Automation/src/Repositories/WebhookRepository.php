<?php

namespace Webkul\Automation\Repositories;

use App\Repositories\TenantAwareRepository;
use Webkul\Automation\Contracts\Webhook;
class WebhookRepository extends TenantAwareRepository
{
    /**
     * Specify Model class name.
     */
    public function model(): string
    {
        return Webhook::class;
    }
}
