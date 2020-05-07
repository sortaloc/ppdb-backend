<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PesertaDidik extends Model
{
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'last_update';
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $connection = 'sqlsrv_2';
    protected $table = 'ppdb.peserta_didik';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'peserta_didik_id';

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
    	'nama_sekolah',
		'npsn',
		'kode_wilayah_sekolah',
		'bentuk',
		'peserta_didik_id',
		'nama',
		'jenis_kelamin',
		'nisn',
		'nik',
		'tempat_lahir',
		'tanggal_lahir',
		'usia',
		'agama_id',
		'kewarganegaraan',
		'kebutuhan_khusus_id',
		'alamat_jalan_pd',
		'rt_pd',
		'rw_pd',
		'nama_dusun_pd',
		'desa_kelurahan_pd',
		'kode_kec_pd',
		'jenis_tinggal_id',
		'alat_transportasi_id',
		'penerima_KPS',
		'no_KIP',
		'nm_KIP',
		'no_KKS',
		'reg_akta_lahir',
		'nama_ayah',
		'pekerjaan_id_ayah',
		'penghasilan_id_ayah',
		'nama_ibu_kandung',
		'pekerjaan_id_ibu',
		'penghasilan_id_ibu',
		'nama_wali',
		'pekerjaan_id_wali',
		'penghasilan_id_wali',
		'lintang',
		'bujur',
		'tingkat_pendidikan_id'
    ];

    /**
     * The connection name for the model.
     * 
     * @var string
     */
}
