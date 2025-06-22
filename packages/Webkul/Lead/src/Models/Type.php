<?php

namespace Webkul\Lead\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Type as TypeContract;

class Type extends Model implements TypeContract
{
    
    use BelongsToTenant;

    protected $table = 'lead_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the leads.
     */
    public function leads()
    {
        return $this->hasMany(LeadProxy::modelClass());
    }
}
