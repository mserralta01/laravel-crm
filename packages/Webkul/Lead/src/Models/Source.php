<?php

namespace Webkul\Lead\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Source as SourceContract;

class Source extends Model implements SourceContract
{
    
    use BelongsToTenant;

    protected $table = 'lead_sources';

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
