<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Jalur extends Model
{
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'last_update';
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $connection = 'sqlsrv_2';
    protected $table = 'ref.jalur';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'jalur_id';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     * 
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
    	'jalur_id',
		'nama',
		'create_date',
		'last_update',
		'expired_date',
		'induk_jalur_id',
		'level_jalur',
    ];

    /**
     * The connection name for the model.
     * 
     * @var string
     */
}
