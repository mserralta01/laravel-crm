<?php

namespace Webkul\EmailTemplate\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\EmailTemplate\Contracts\EmailTemplate as EmailTemplateContract;

class EmailTemplate extends Model implements EmailTemplateContract
{
    
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'subject',
        'content',
    ];
}
