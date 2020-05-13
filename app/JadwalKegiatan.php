<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JadwalKegiatan extends Model
{
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'last_update';
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $connection = 'sqlsrv_2';
    protected $table = 'ppdb.jadwal_kegiatan';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'jadwal_kegiatan_id';

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
    	'jadwal_kegiatan_id',
		'periode_kegiatan_id',
		'nama',
		'kode_wilayah',
		'tanggal_mulai',
		'tanggal_selesai',
		'create_date',
		'last_update',
		'soft_delete',
		'pengguna_id',
		'jalur_id',
    ];

    /**
     * The connection name for the model.
     * 
     * @var string
     */
}
