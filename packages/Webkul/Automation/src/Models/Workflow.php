<?php

namespace Webkul\Automation\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Automation\Contracts\Workflow as WorkflowContract;

class Workflow extends Model implements WorkflowContract
{
    
    use BelongsToTenant;

    protected $casts = [
        'conditions' => 'array',
        'actions'    => 'array',
    ];

    protected $fillable = [
        'name',
        'description',
        'entity_type',
        'event',
        'condition_type',
        'conditions',
        'actions',
    ];
}
