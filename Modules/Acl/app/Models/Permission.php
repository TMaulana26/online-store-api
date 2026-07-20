<?php

namespace Modules\Acl\Models;

use App\Traits\HasActiveStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Translatable\HasTranslations;

class Permission extends SpatiePermission
{
    use HasActiveStatus, HasTranslations, SoftDeletes;

    public $translatable = ['display_name'];

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'display_name',
        'guard_name',
        'menu',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
