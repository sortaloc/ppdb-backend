<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PilihanSekolah extends Model
{
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'last_update';
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $connection = 'sqlsrv_2';
    protected $table = 'ppdb.pilihan_sekolah';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'pilihan_sekolah_id';

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
    	'pilihan_sekolah_id',
		'sekolah_id',
		'calon_peserta_didik_id',
		'jalur_id',
		'urut_pilihan',
		'create_date',
		'last_update',
		'soft_delete'
    ];

    /**
     * The connection name for the model.
     * 
     * @var string
     */
}
