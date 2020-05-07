<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CalonPesertaDidik extends Model
{
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'last_update';
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $connection = 'sqlsrv_2';
    protected $table = 'ppdb.calon_peserta_didik';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'calon_peserta_didik_id';

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
    	'calon_peserta_didik_id',
		'create_date',
		'last_update',
		'soft_delete',
		'nik',
		'jenis_kelamin',
		'tempat_lahir',
		'tanggal_lahir',
		'asal_sekolah_id',
		'alamat_tempat_tinggal',
		'kode_wilayah',
		'kode_pos',
		'lintang',
		'bujur',
		'nama_ayah',
		'tempat_lahir_ayah',
		'tanggal_lahir_ayah',
		'pendidikan_terakhir_id_ayah',
		'pekerjaan_id_ayah',
		'alamat_tempat_tinggal_ayah',
		'no_telepon_ayah',
		'nama_ibu',
		'tempat_lahir_ibu',
		'pendidikan_terakhir_id_ibu',
		'pekerjaan_id_ibu',
		'alamat_tempat_tinggal_ibu',
		'no_telepon_ibu',
		'nama_wali',
		'tempat_lahir_wali',
		'tanggal_lahir_wali',
		'pekerjaan_id_wali',
		'tanggal_lahir_ibu',
		'alamat_tempat_tinggal_wali',
		'no_telepon_wali',
		'orang_tua_utama',
		'rt',
		'rw'
    ];

    /**
     * The connection name for the model.
     * 
     * @var string
     */
}
