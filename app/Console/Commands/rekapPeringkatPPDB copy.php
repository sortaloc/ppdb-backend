<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class rekapPeringkatPPDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rekap:peringkat_ppdb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 0
        $exe_0_00 = DB::connection('sqlsrv_2')->statement("WITH cte AS ( 
        SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY status_terima ASC, last_update DESC ) AS urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 ) 
        UPDATE ppdb.peserta_didik_diterima 
        SET soft_delete = 8 
        WHERE
            peserta_didik_diterima_id IN (
        SELECT
            peserta_didik_diterima_id 
        FROM
            cte 
        WHERE
            urutan > 1 
        )");
        if($exe_0_00){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DELETE pd_diterima ganda ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DELETE pd_diterima ganda ...".PHP_EOL;
        }
        
        $exe_0_0 = DB::connection('sqlsrv_2')->statement("DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1");
        $exe_0_1 = DB::connection('sqlsrv_2')->statement("SELECT
                    calon.calon_peserta_didik_id,
                    calon.nama,
                    peringkatan.jalur_id,
                    peringkatan.nama_jalur,	
                    peringkatan.no_urut_final,
                    (case when peringkatan.no_urut_final <= peringkatan.kuota_sekolah THEN 'MASUK' else 'TERDEGRADASI' END) as status_final,
                    peringkatan.sekolah_id,
                    peringkatan.sekolah_penerima,
                    peringkatan.no_urut_penerimaan,
                    peringkatan.kuota_sekolah,
                    peringkatan.jarak,
                    peringkatan.jarak_km,
                    peringkatan.rd,
                    peringkatan.urut_dipilih,
                    now() as tanggal_rekap
                INTO rekap.peringkat_ppdb_tahap_1
                FROM
                    ppdb.calon_peserta_didik calon
                    LEFT JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calon.calon_peserta_didik_id AND pd_diterima.urutan = 1 
                    -- LEFT JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calon.calon_peserta_didik_id 
                    LEFT JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calon.calon_peserta_didik_id
                    LEFT JOIN (
                    SELECT finals.* FROM (
                    SELECT 
                        ROW_NUMBER
                        () OVER (
                                PARTITION BY aa.sekolah_id, aa.jalur_id
                        ORDER BY
            -- 					aa.urut_dipilih asc, aa.no_urut_penerimaan ASC
                            aa.no_urut_penerimaan ASC
                        ) no_urut_final,
                        * 
                    FROM (
                    SELECT
                        calon.calon_peserta_didik_id,
                        calon.nama,
                        (CASE 
                                    WHEN ranking_zonasi.sekolah_id is not null then ranking_zonasi.sekolah_id
                                    WHEN ranking_affirmasi.sekolah_id is not null then ranking_affirmasi.sekolah_id
                                    WHEN ranking_pindah.sekolah_id is not null then ranking_pindah.sekolah_id
                                    WHEN ranking_minat.sekolah_id is not null then ranking_minat.sekolah_id
                                    WHEN ranking_tahfidz.sekolah_id is not null then ranking_tahfidz.sekolah_id
                                    ELSE NULL
                        END) as sekolah_id,
                        (CASE 
                                    WHEN ranking_zonasi.nama_sekolah is not null then ranking_zonasi.nama_sekolah
                                    WHEN ranking_affirmasi.nama_sekolah is not null then ranking_affirmasi.nama_sekolah
                                    WHEN ranking_pindah.nama_sekolah is not null then ranking_pindah.nama_sekolah
                                    WHEN ranking_minat.nama_sekolah is not null then ranking_minat.nama_sekolah
                                    WHEN ranking_tahfidz.nama_sekolah is not null then ranking_tahfidz.nama_sekolah
                                    ELSE NULL
                        END) as sekolah_penerima,
                        (CASE 
                                    WHEN ranking_zonasi.ranking_sekolah is not null then ranking_zonasi.ranking_sekolah
                                    WHEN ranking_affirmasi.ranking_sekolah is not null then ranking_affirmasi.ranking_sekolah
                                    WHEN ranking_pindah.ranking_sekolah is not null then ranking_pindah.ranking_sekolah
                                    WHEN ranking_minat.ranking_sekolah is not null then ranking_minat.ranking_sekolah
                                    WHEN ranking_tahfidz.ranking_sekolah is not null then ranking_tahfidz.ranking_sekolah
                                    ELSE NULL
                        END) as no_urut_penerimaan,
                        (CASE 
                                    WHEN ranking_zonasi.kuota is not null then ranking_zonasi.kuota
                                    WHEN ranking_affirmasi.kuota is not null then ranking_affirmasi.kuota
                                    WHEN ranking_pindah.kuota is not null then ranking_pindah.kuota
                                    WHEN ranking_minat.kuota is not null then ranking_minat.kuota
                                    WHEN ranking_tahfidz.kuota is not null then ranking_tahfidz.kuota
                                    ELSE NULL
                        END) as kuota_sekolah,
                        (CASE 
                                    WHEN ranking_zonasi.nama_jalur is not null then ranking_zonasi.nama_jalur
                                    WHEN ranking_affirmasi.nama_jalur is not null then ranking_affirmasi.nama_jalur
                                    WHEN ranking_pindah.nama_jalur is not null then ranking_pindah.nama_jalur
                                    WHEN ranking_minat.nama_jalur is not null then ranking_minat.nama_jalur
                                    WHEN ranking_tahfidz.nama_jalur is not null then ranking_tahfidz.nama_jalur
                                    ELSE NULL
                        END) as nama_jalur,
                        (CASE 
                                    WHEN ranking_zonasi.jalur_id is not null then ranking_zonasi.jalur_id
                                    WHEN ranking_affirmasi.jalur_id is not null then ranking_affirmasi.jalur_id
                                    WHEN ranking_pindah.jalur_id is not null then ranking_pindah.jalur_id
                                    WHEN ranking_minat.jalur_id is not null then ranking_minat.jalur_id
                                    WHEN ranking_tahfidz.jalur_id is not null then ranking_tahfidz.jalur_id
                                    ELSE NULL
                        END) as jalur_id,
                        (CASE 
                                    WHEN ranking_zonasi.jarak is not null then ranking_zonasi.jarak
                                    WHEN ranking_affirmasi.jarak is not null then ranking_affirmasi.jarak
                                    WHEN ranking_pindah.jarak is not null then ranking_pindah.jarak
                                    WHEN ranking_minat.jarak is not null then ranking_minat.jarak
                                    WHEN ranking_tahfidz.jarak is not null then ranking_tahfidz.jarak
                                    ELSE NULL 
                        END) as jarak,
                        (CASE 
                                    WHEN ranking_zonasi.jarak_km is not null then ranking_zonasi.jarak_km
                                    WHEN ranking_affirmasi.jarak_km is not null then ranking_affirmasi.jarak_km
                                    WHEN ranking_pindah.jarak_km is not null then ranking_pindah.jarak_km
                                    WHEN ranking_minat.jarak_km is not null then ranking_minat.jarak_km
                                    WHEN ranking_tahfidz.jarak_km is not null then ranking_tahfidz.jarak_km
                                    ELSE NULL
                        END) as jarak_km,
                        ranking_zonasi.rd,
                        ranking_zonasi.urut_dipilih
                    FROM
                        ppdb.calon_peserta_didik calon
                        LEFT JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calon.calon_peserta_didik_id AND pd_diterima.urutan = 1
                        -- LEFT JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calon.calon_peserta_didik_id 
                        LEFT JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calon.calon_peserta_didik_id
                        
                        -- join untuk zonasi
                        LEFT JOIN (
                            SELECT
                                ROW_NUMBER
                                () OVER (
                                        PARTITION BY ranking_sekolah.calon_peserta_didik_id
                                ORDER BY
                                        ranking_diri.ranking_diri ASC
                                ) urut_dipilih,
                                ranking_diri.ranking_diri as ranking_diri,
                                (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                                jalur.nama as nama_jalur,
                                ranking_sekolah.*,
                                ranking_diri.ranking_diri as rd
                            FROM
                                ppdb.pilihan_sekolah
                            JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                            JOIN (
                                SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                                    ORDER BY
                                            -- pilihan.urut_pilihan ASC,
                                            ppdb.calculate_distance (
                                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                                    'Meter' 
                                            ) ASC
                                    ) AS ranking_sekolah,
                                    -- kuota.kuota,
                                    (CASE 
                                            WHEN pilihan.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN pilihan.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN pilihan.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN pilihan.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN pilihan.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as kuota,
                                    pilihan.urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                                FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                                WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id = '0400'
                                    AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                            ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.calon_peserta_didik_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC
                                    )	AS ranking_diri,
                                    pilihan.urut_pilihan as urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id = '0400'
                            ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            WHERE
                                    ppdb.pilihan_sekolah.soft_delete = 0
                	        -- AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                -- 			AND ranking_diri.ranking_diri = 1
                            ) ranking_zonasi on ranking_zonasi.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                -- 			AND ranking_zonasi.urut_dipilih = 1
        
                            -- join untuk affirmasi
                            LEFT JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY ranking_sekolah.calon_peserta_didik_id
                                    ORDER BY
                                            ranking_diri.ranking_diri ASC
                                    ) urut_dipilih,
                                    ranking_diri.ranking_diri as ranking_diri,
                                    (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                                    jalur.nama as nama_jalur,
                                    ranking_sekolah.*
                            FROM
                                    ppdb.pilihan_sekolah
                            JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC,
                                            ppdb.calculate_distance (
                                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                                    'Meter' 
                                            ) ASC
                                    ) AS ranking_sekolah,
                                    -- kuota.kuota,
                                    (CASE 
                                            WHEN pilihan.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN pilihan.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN pilihan.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN pilihan.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN pilihan.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as kuota,
                                    pilihan.urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 		
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id = '0100'
                                    AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                            ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.calon_peserta_didik_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC
                                    )	AS ranking_diri,
                                    pilihan.urut_pilihan as urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id = '0100'
                            ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            WHERE
                                    ppdb.pilihan_sekolah.soft_delete = 0
                                    AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                            ) ranking_affirmasi on ranking_affirmasi.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                            AND ranking_affirmasi.urut_dipilih = 1
        
                            -- join untuk perpindahan orang tua
                            LEFT JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY ranking_sekolah.calon_peserta_didik_id
                                    ORDER BY
                                            ranking_diri.ranking_diri ASC
                                    ) urut_dipilih,
                                    ranking_diri.ranking_diri as ranking_diri,
                                    (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                                    jalur.nama as nama_jalur,
                                    ranking_sekolah.*
                            FROM
                                    ppdb.pilihan_sekolah
                            JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC,
                                            ppdb.calculate_distance (
                                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                                    'Meter' 
                                            ) ASC
                                    ) AS ranking_sekolah,
                                    -- kuota.kuota,
                                    (CASE 
                                            WHEN pilihan.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN pilihan.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN pilihan.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN pilihan.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN pilihan.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as kuota,
                                    pilihan.urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 		
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id = '0200'
                                    AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                            ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.calon_peserta_didik_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC
                                    )	AS ranking_diri,
                                    pilihan.urut_pilihan as urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id = '0200'
                            ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            WHERE
                                    ppdb.pilihan_sekolah.soft_delete = 0
                                    AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                            ) ranking_pindah on ranking_pindah.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                            AND ranking_pindah.urut_dipilih = 1
        
                            -- join untuk minat bakat
                            LEFT JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY ranking_sekolah.calon_peserta_didik_id
                                    ORDER BY
                                            ranking_diri.ranking_diri ASC
                                    ) urut_dipilih,
                                    ranking_diri.ranking_diri as ranking_diri,
                                    (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                                    jalur.nama as nama_jalur,
                                    ranking_sekolah.*
                            FROM
                                    ppdb.pilihan_sekolah
                            JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC,
                                            ppdb.calculate_distance (
                                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                                    'Meter' 
                                            ) ASC
                                    ) AS ranking_sekolah,
                                    (CASE 
                                            WHEN pilihan.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN pilihan.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN pilihan.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN pilihan.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN pilihan.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as kuota,
                                    pilihan.urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id IN ('0300')
                                    AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                            ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.calon_peserta_didik_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC
                                    )	AS ranking_diri,
                                    pilihan.urut_pilihan as urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	 	
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id IN ('0300')
                            ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            WHERE
                                    ppdb.pilihan_sekolah.soft_delete = 0
                             AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                            ) ranking_minat on ranking_minat.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                            AND ranking_minat.urut_dipilih = 1

                            -- join untuk tahfidz
                            LEFT JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY ranking_sekolah.calon_peserta_didik_id
                                    ORDER BY
                                            ranking_diri.ranking_diri ASC
                                    ) urut_dipilih,
                                    ranking_diri.ranking_diri as ranking_diri,
                                    (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                                    jalur.nama as nama_jalur,
                                    ranking_sekolah.*
                            FROM
                                    ppdb.pilihan_sekolah
                            JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC,
                                            ppdb.calculate_distance (
                                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                                    'Meter' 
                                            ) ASC
                                    ) AS ranking_sekolah,
                                    (CASE 
                                            WHEN pilihan.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN pilihan.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN pilihan.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN pilihan.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN pilihan.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as kuota,
                                    pilihan.urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id IN ('0500')
                                    AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                            ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            JOIN (
                            SELECT
                                    ROW_NUMBER
                                    () OVER (
                                            PARTITION BY pilihan.calon_peserta_didik_id
                                    ORDER BY
                                            pilihan.urut_pilihan ASC
                                    )	AS ranking_diri,
                                    pilihan.urut_pilihan as urut_pilihan,
                                    calons.nama,
                                    sekolah.nama AS nama_sekolah,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) AS jarak,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'K' 
                                    ) AS jarak_km,
                                    pilihan.* 
                            FROM
                                    ppdb.pilihan_sekolah pilihan
                                    JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                                    JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                                    JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id
                                    JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                                    -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                                    JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	 	
                            WHERE
                                    pilihan.soft_delete = 0 
                                    AND calons.soft_delete = 0 
                                    AND pilihan.jalur_id IN ('0500')
                            ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                            AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                            WHERE
                                    ppdb.pilihan_sekolah.soft_delete = 0
                             AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                            ) ranking_tahfidz on ranking_tahfidz.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                            AND ranking_tahfidz.urut_dipilih = 1
                    WHERE
                        calon.soft_delete = 0
                    AND pd_konfirmasi.calon_peserta_didik_id IS NOT NULL
                    AND pd_diterima.peserta_didik_id IS NOT NULL
                    AND pd_diterima.status_terima = 1
                    ) aa
                    ) finals
                    ) peringkatan on peringkatan.calon_peserta_didik_id = calon.calon_peserta_didik_id
                    WHERE
                        calon.soft_delete = 0
                        AND pd_konfirmasi.calon_peserta_didik_id IS NOT NULL
                    ORDER BY 
                        peringkatan.jalur_id, 
                        peringkatan.urut_dipilih");
        
        
        if($exe_0_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1 ...".PHP_EOL;
        }
        if($exe_0_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1 ...".PHP_EOL;
        }

        // 1
        $exe_1_0 = DB::connection('sqlsrv_2')->statement("DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_masuk");
        $exe_1_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *
                                                        INTO rekap.peringkat_ppdb_tahap_1_masuk
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1 
                                                        WHERE 
                                                            status_final = 'MASUK'");
        if($exe_1_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_masuk ...".PHP_EOL;
        }
        if($exe_1_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_masuk ...".PHP_EOL;
        }

        // 2
        $exe_2_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_1_degradasi");
        // $exe_2_1 = DB::connection('sqlsrv_2')->statement("SELECT
        //                                                     *
        //                                                 INTO rekap.peringkat_ppdb_tahap_1_degradasi
        //                                                 FROM
        //                                                     rekap.peringkat_ppdb_tahap_1 
        //                                                 WHERE 
        //                                                     status_final = 'TERDEGRADASI'");
        $exe_2_1 = DB::connection('sqlsrv_2')->statement("
            with cte as (
            SELECT
                    *
            FROM
                    rekap.peringkat_ppdb_tahap_1 
            WHERE 
                    status_final = 'TERDEGRADASI'
                and sekolah_id is not null
                    UNION
            SELECT 
                rekap.peringkat_ppdb_tahap_1.calon_peserta_didik_id,
                rekap.peringkat_ppdb_tahap_1.nama,
                pilihan.jalur_id,
                jalur.nama as nama_jalur,
                null as no_urut_final,
                rekap.peringkat_ppdb_tahap_1.status_final,
                pilihan.sekolah_id,
                sekolah.nama as sekolah_penerima,
                null as no_urut_penerimaan,
                null as kuota_sekolah,
                null as jarak,
                null as jarak_km,
                null as rd,
                pilihan.urut_pilihan as urut_dipilih,
                rekap.peringkat_ppdb_tahap_1.tanggal_rekap
            FROM
                rekap.peringkat_ppdb_tahap_1
            JOIN ppdb.pilihan_sekolah pilihan on pilihan.calon_peserta_didik_id = rekap.peringkat_ppdb_tahap_1.calon_peserta_didik_id
            JOIN ref.jalur jalur on jalur.jalur_id = pilihan.jalur_id
            JOIN ppdb.sekolah sekolah on sekolah.sekolah_id = pilihan.sekolah_id
            WHERE
                status_final = 'TERDEGRADASI'
            and rekap.peringkat_ppdb_tahap_1.sekolah_id is null
            )
            select 
                * 
            INTO rekap.peringkat_ppdb_tahap_1_degradasi	
            from 
                cte");
        if($exe_2_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_degradasi ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_degradasi ...".PHP_EOL;
        }
        if($exe_2_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_degradasi ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_degradasi ...".PHP_EOL;
        }

        // 3
        $exe_3_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_1_masuk_diambil");
        $exe_3_1 = DB::connection('sqlsrv_2')->statement("SELECT 
                                                            peringkat_ppdb_tahap_1_masuk.*
                                                        INTO rekap.peringkat_ppdb_tahap_1_masuk_diambil
                                                        FROM (
                                                            SELECT
                                                                ROW_NUMBER
                                                                    () OVER (
                                                                            PARTITION BY peringkat_ppdb_tahap_1_masuk.calon_peserta_didik_id
                                                                    ORDER BY
                                                                            peringkat_ppdb_tahap_1_masuk.urut_dipilih ASC
                                                                ) urutan_diambil,
                                                                * 
                                                            FROM
                                                                rekap.peringkat_ppdb_tahap_1_masuk 
                                                        ) peringkat_ppdb_tahap_1_masuk
                                                        where peringkat_ppdb_tahap_1_masuk.urutan_diambil = 1");
        if($exe_3_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_masuk_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_masuk_diambil ...".PHP_EOL;
        }
        if($exe_3_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_masuk_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_masuk_diambil ...".PHP_EOL;
        }

        // 4
        $exe_4_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_1_2");
        $exe_4_1 = DB::connection('sqlsrv_2')->statement("select
                                                            *
                                                        INTO rekap.peringkat_ppdb_tahap_1_2
                                                        from rekap.peringkat_ppdb_tahap_1_masuk_diambil");
        $exe_4_2 = DB::connection('sqlsrv_2')->statement("ALTER TABLE rekap.peringkat_ppdb_tahap_1_2 
                                                        ADD PRIMARY KEY (calon_peserta_didik_id)");
        if($exe_4_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }
        if($exe_4_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }
        if($exe_4_2){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] ADD PRIMARY KEY rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] ADD PRIMARY KEY rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }

        // 5
        $exe_5_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.sisa_kuota_tahap_1");
        $exe_5_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            rekap.peringkat_ppdb_tahap_1_masuk_diambil.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id,
                                                            SUM ( 1 ) AS jumlah,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) as kuota,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) - SUM ( 1 ) as sisa
                                                        INTO rekap.sisa_kuota_tahap_1
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_masuk_diambil
                                                        JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_masuk_diambil.sekolah_id
                                                        -- WHERE
                                                        -- 	rekap.peringkat_ppdb_tahap_1_masuk_diambil.sekolah_id = 'c0b6e4b9-8c18-e111-87e6-97130693b061' 
                                                        GROUP BY
                                                            rekap.peringkat_ppdb_tahap_1_masuk_diambil.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_masuk_diambil.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END)
                                                        ORDER BY sekolah_id");
        
        if($exe_5_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.sisa_kuota_tahap_1 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.sisa_kuota_tahap_1 ...".PHP_EOL;
        }
        if($exe_5_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.sisa_kuota_tahap_1 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.sisa_kuota_tahap_1 ...".PHP_EOL;
        }

        // 6
        $exe_6_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk");
        $exe_6_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *	
                                                        INTO rekap.peringkat_ppdb_tahap_2_masuk
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_degradasi 
                                                        WHERE
                                                            calon_peserta_didik_id NOT IN (
                                                            SELECT
                                                                calon_peserta_didik_id 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2)");
        
        if($exe_6_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk ...".PHP_EOL;
        }
        if($exe_6_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_2_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_2_masuk ...".PHP_EOL;
        }

        // 7
        $exe_7_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk_sekolah");
        $exe_7_1 = DB::connection('sqlsrv_2')->statement("SELECT 
                                                            (CASE WHEN peringkat_ppdb_tahap_2_masuk_sekolah.ranking_sekolah <= peringkat_ppdb_tahap_2_masuk_sekolah.sisa then 'MASUK' else 'TERDEGRADASI' END) as status_final_tahap_2,
                                                            * 
                                                        INTO rekap.peringkat_ppdb_tahap_2_masuk_sekolah
                                                        FROM (
                                                        SELECT ROW_NUMBER
                                                            () OVER ( PARTITION BY rekap.peringkat_ppdb_tahap_2_masuk.sekolah_id ORDER BY rekap.peringkat_ppdb_tahap_2_masuk.no_urut_final ASC ) ranking_sekolah,
                                                            sisa_kuota.sisa,
                                                            rekap.peringkat_ppdb_tahap_2_masuk.* 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_2_masuk
                                                            JOIN rekap.sisa_kuota_tahap_1 sisa_kuota ON sisa_kuota.sekolah_id = rekap.peringkat_ppdb_tahap_2_masuk.sekolah_id 
                                                            AND sisa_kuota.jalur_id = rekap.peringkat_ppdb_tahap_2_masuk.jalur_id 
                                                        ) peringkat_ppdb_tahap_2_masuk_sekolah");
        
        if($exe_7_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk_sekolah ...".PHP_EOL;
        }
        if($exe_7_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_2_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_2_masuk_sekolah ...".PHP_EOL;
        }

        // 8
        $exe_8_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk_sekolah_diambil");
        $exe_8_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *
                                                        INTO rekap.peringkat_ppdb_tahap_2_masuk_sekolah_diambil
                                                        FROM
                                                        (
                                                            SELECT
                                                                ROW_NUMBER
                                                                    () OVER (
                                                                            PARTITION BY peringkat_ppdb_tahap_2_masuk_sekolah.calon_peserta_didik_id
                                                                    ORDER BY
                                                                            peringkat_ppdb_tahap_2_masuk_sekolah.urut_dipilih ASC
                                                                ) urutan_diambil,
                                                                *
                                                            FROM
                                                                rekap.peringkat_ppdb_tahap_2_masuk_sekolah 
                                                            WHERE
                                                                    status_final_tahap_2 = 'MASUK'
                                                        ) peringkat_ppdb_tahap_2_masuk_sekolah_diambil
                                                        WHERE
                                                            peringkat_ppdb_tahap_2_masuk_sekolah_diambil.urutan_diambil = 1");
        
        if($exe_8_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_2_masuk_sekolah_diambil ...".PHP_EOL;
        }
        if($exe_8_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_2_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_2_masuk_sekolah_diambil ...".PHP_EOL;
        }

        // 9
        $exe_9_1 = DB::connection('sqlsrv_2')->statement("INSERT INTO rekap.peringkat_ppdb_tahap_1_2 SELECT
                                                            urutan_diambil,
                                                            calon_peserta_didik_id,
                                                            nama,
                                                            jalur_id,
                                                            nama_jalur,
                                                            no_urut_final,
                                                            status_final,
                                                            sekolah_id,
                                                            sekolah_penerima,
                                                            no_urut_penerimaan,
                                                            kuota_sekolah,
                                                            jarak,
                                                            jarak_km,
                                                            rd,
                                                            urut_dipilih
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_2_masuk_sekolah_diambil");
        
        if($exe_9_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }

        // 10
        $exe_10_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.sisa_kuota_tahap_2");
        $exe_10_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            SUM ( 1 ) AS jumlah,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) as kuota,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) - SUM ( 1 ) as sisa
                                                        INTO rekap.sisa_kuota_tahap_2
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2
                                                        JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                                        GROUP BY
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END)");
        
        if($exe_10_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.sisa_kuota_tahap_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.sisa_kuota_tahap_2 ...".PHP_EOL;
        }
        if($exe_10_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.sisa_kuota_tahap_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.sisa_kuota_tahap_2 ...".PHP_EOL;
        }

        // 11
        $exe_11_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk");
        $exe_11_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *	
                                                        INTO rekap.peringkat_ppdb_tahap_3_masuk
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_degradasi 
                                                        WHERE
                                                            calon_peserta_didik_id NOT IN (
                                                            SELECT
                                                                calon_peserta_didik_id 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2)");
        
        if($exe_11_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk ...".PHP_EOL;
        }
        if($exe_11_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_3_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_3_masuk ...".PHP_EOL;
        }

        // 12
        $exe_12_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk_sekolah");
        $exe_12_1 = DB::connection('sqlsrv_2')->statement("SELECT 
                                                            (CASE WHEN peringkat_ppdb_tahap_3_masuk_sekolah.ranking_sekolah <= peringkat_ppdb_tahap_3_masuk_sekolah.sisa then 'MASUK' else 'TERDEGRADASI' END) as status_final_tahap_3,
                                                            * 
                                                        INTO rekap.peringkat_ppdb_tahap_3_masuk_sekolah
                                                        FROM (
                                                        SELECT ROW_NUMBER
                                                            () OVER ( PARTITION BY rekap.peringkat_ppdb_tahap_3_masuk.sekolah_id ORDER BY rekap.peringkat_ppdb_tahap_3_masuk.no_urut_final ASC ) ranking_sekolah,
                                                            sisa_kuota.sisa,
                                                            rekap.peringkat_ppdb_tahap_3_masuk.* 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_3_masuk
                                                            JOIN rekap.sisa_kuota_tahap_2 sisa_kuota ON sisa_kuota.sekolah_id = rekap.peringkat_ppdb_tahap_3_masuk.sekolah_id 
                                                            AND sisa_kuota.jalur_id = rekap.peringkat_ppdb_tahap_3_masuk.jalur_id 
                                                        ) peringkat_ppdb_tahap_3_masuk_sekolah");
        
        if($exe_12_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk_sekolah ...".PHP_EOL;
        }
        if($exe_12_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_3_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_3_masuk_sekolah ...".PHP_EOL;
        }

        // 13
        $exe_13_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk_sekolah_diambil");
        $exe_13_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *
                                                        INTO rekap.peringkat_ppdb_tahap_3_masuk_sekolah_diambil
                                                        FROM
                                                        (
                                                            SELECT
                                                                ROW_NUMBER
                                                                    () OVER (
                                                                            PARTITION BY peringkat_ppdb_tahap_3_masuk_sekolah.calon_peserta_didik_id
                                                                    ORDER BY
                                                                            peringkat_ppdb_tahap_3_masuk_sekolah.urut_dipilih ASC
                                                                ) urutan_diambil,
                                                                *
                                                            FROM
                                                                rekap.peringkat_ppdb_tahap_3_masuk_sekolah 
                                                            WHERE
                                                                    status_final_tahap_3 = 'MASUK'
                                                        ) peringkat_ppdb_tahap_3_masuk_sekolah_diambil
                                                        WHERE
                                                            peringkat_ppdb_tahap_3_masuk_sekolah_diambil.urutan_diambil = 1");
        
        if($exe_13_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_3_masuk_sekolah_diambil ...".PHP_EOL;
        }
        if($exe_13_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_3_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_3_masuk_sekolah_diambil ...".PHP_EOL;
        }

        // 14
        $exe_14_0 = DB::connection('sqlsrv_2')->statement("INSERT INTO rekap.peringkat_ppdb_tahap_1_2 SELECT
                                                            urutan_diambil,
                                                            calon_peserta_didik_id,
                                                            nama,
                                                            jalur_id,
                                                            nama_jalur,
                                                            no_urut_final,
                                                            status_final,
                                                            sekolah_id,
                                                            sekolah_penerima,
                                                            no_urut_penerimaan,
                                                            kuota_sekolah,
                                                            jarak,
                                                            jarak_km,
                                                            rd,
                                                            urut_dipilih
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_3_masuk_sekolah_diambil");
        
        if($exe_14_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }
        
        // 15
        $exe_15_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.sisa_kuota_tahap_3");
        $exe_15_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            SUM ( 1 ) AS jumlah,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) as kuota,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) - SUM ( 1 ) as sisa
                                                        INTO rekap.sisa_kuota_tahap_3
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2
                                                        JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                                        GROUP BY
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END)");
        
        if($exe_15_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.sisa_kuota_tahap_3 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.sisa_kuota_tahap_3 ...".PHP_EOL;
        }
        if($exe_15_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.sisa_kuota_tahap_3 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.sisa_kuota_tahap_3 ...".PHP_EOL;
        }

        // 16
        $exe_16_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk");
        $exe_16_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *	
                                                        INTO rekap.peringkat_ppdb_tahap_4_masuk
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_degradasi 
                                                        WHERE
                                                            calon_peserta_didik_id NOT IN (
                                                            SELECT
                                                                calon_peserta_didik_id 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2)");
        
        if($exe_16_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk ...".PHP_EOL;
        }
        if($exe_16_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_4_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_4_masuk ...".PHP_EOL;
        }

        // 17
        $exe_17_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk_sekolah");
        $exe_17_1 = DB::connection('sqlsrv_2')->statement("SELECT 
                                                            (CASE WHEN peringkat_ppdb_tahap_4_masuk_sekolah.ranking_sekolah <= peringkat_ppdb_tahap_4_masuk_sekolah.sisa then 'MASUK' else 'TERDEGRADASI' END) as status_final_tahap_4,
                                                            * 
                                                        INTO rekap.peringkat_ppdb_tahap_4_masuk_sekolah
                                                        FROM (
                                                        SELECT ROW_NUMBER
                                                            () OVER ( PARTITION BY rekap.peringkat_ppdb_tahap_4_masuk.sekolah_id ORDER BY rekap.peringkat_ppdb_tahap_4_masuk.no_urut_final ASC ) ranking_sekolah,
                                                            sisa_kuota.sisa,
                                                            rekap.peringkat_ppdb_tahap_4_masuk.* 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_4_masuk
                                                            JOIN rekap.sisa_kuota_tahap_3 sisa_kuota ON sisa_kuota.sekolah_id = rekap.peringkat_ppdb_tahap_4_masuk.sekolah_id 
                                                            AND sisa_kuota.jalur_id = rekap.peringkat_ppdb_tahap_4_masuk.jalur_id 
                                                        ) peringkat_ppdb_tahap_4_masuk_sekolah");
        
        if($exe_17_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk_sekolah ...".PHP_EOL;
        }
        if($exe_17_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_4_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_4_masuk_sekolah ...".PHP_EOL;
        }

        // 18
        $exe_18_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk_sekolah_diambil");
        $exe_18_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *
                                                        INTO rekap.peringkat_ppdb_tahap_4_masuk_sekolah_diambil
                                                        FROM
                                                        (
                                                            SELECT
                                                                ROW_NUMBER
                                                                    () OVER (
                                                                            PARTITION BY peringkat_ppdb_tahap_4_masuk_sekolah.calon_peserta_didik_id
                                                                    ORDER BY
                                                                            peringkat_ppdb_tahap_4_masuk_sekolah.urut_dipilih ASC
                                                                ) urutan_diambil,
                                                                *
                                                            -- 	calon_peserta_didik_id,
                                                            -- 	SUM ( 1 ) AS jumlah 
                                                            FROM
                                                                rekap.peringkat_ppdb_tahap_4_masuk_sekolah 
                                                            WHERE
                                                                    status_final_tahap_4 = 'MASUK'
                                                        -- 	AND calon_peserta_didik_id = '007841d0-b914-4a41-b07d-fab46c45e854'
                                                        ) peringkat_ppdb_tahap_4_masuk_sekolah_diambil
                                                        WHERE
                                                            peringkat_ppdb_tahap_4_masuk_sekolah_diambil.urutan_diambil = 1");
        
        if($exe_18_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_4_masuk_sekolah_diambil ...".PHP_EOL;
        }
        if($exe_18_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_4_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_4_masuk_sekolah_diambil ...".PHP_EOL;
        }

        // 19
        $exe_19_0 = DB::connection('sqlsrv_2')->statement("INSERT INTO rekap.peringkat_ppdb_tahap_1_2 SELECT
                                                            urutan_diambil,
                                                            calon_peserta_didik_id,
                                                            nama,
                                                            jalur_id,
                                                            nama_jalur,
                                                            no_urut_final,
                                                            status_final,
                                                            sekolah_id,
                                                            sekolah_penerima,
                                                            no_urut_penerimaan,
                                                            kuota_sekolah,
                                                            jarak,
                                                            jarak_km,
                                                            rd,
                                                            urut_dipilih
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_4_masuk_sekolah_diambil");        
        if($exe_19_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }

        // 20
        $exe_20_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.sisa_kuota_tahap_4");
        $exe_20_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            SUM ( 1 ) AS jumlah,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) as kuota,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) - SUM ( 1 ) as sisa
                                                        INTO rekap.sisa_kuota_tahap_4
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2
                                                        JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                                        -- WHERE
                                                        -- 	rekap.peringkat_ppdb_tahap_1_2.sekolah_id = 'c0b6e4b9-8c18-e111-87e6-97130693b061' 
                                                        GROUP BY
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END)");
        
        if($exe_20_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.sisa_kuota_tahap_4 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.sisa_kuota_tahap_4 ...".PHP_EOL;
        }
        if($exe_20_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.sisa_kuota_tahap_4 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.sisa_kuota_tahap_4 ...".PHP_EOL;
        }

        // 21
        $exe_21_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk");
        $exe_21_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *	
                                                        INTO rekap.peringkat_ppdb_tahap_5_masuk
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_degradasi 
                                                        WHERE
                                                            calon_peserta_didik_id NOT IN (
                                                            SELECT
                                                                calon_peserta_didik_id 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2)");
        
        if($exe_21_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk ...".PHP_EOL;
        }
        if($exe_21_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_5_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_5_masuk ...".PHP_EOL;
        }

        // 22
        $exe_22_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk_sekolah");
        $exe_22_1 = DB::connection('sqlsrv_2')->statement("SELECT 
                                                            (CASE WHEN peringkat_ppdb_tahap_5_masuk_sekolah.ranking_sekolah <= peringkat_ppdb_tahap_5_masuk_sekolah.sisa then 'MASUK' else 'TERDEGRADASI' END) as status_final_tahap_5,
                                                            * 
                                                        INTO rekap.peringkat_ppdb_tahap_5_masuk_sekolah
                                                        FROM (
                                                        SELECT ROW_NUMBER
                                                            () OVER ( PARTITION BY rekap.peringkat_ppdb_tahap_5_masuk.sekolah_id ORDER BY rekap.peringkat_ppdb_tahap_5_masuk.no_urut_final ASC ) ranking_sekolah,
                                                            sisa_kuota.sisa,
                                                            rekap.peringkat_ppdb_tahap_5_masuk.* 
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_5_masuk
                                                            JOIN rekap.sisa_kuota_tahap_4 sisa_kuota ON sisa_kuota.sekolah_id = rekap.peringkat_ppdb_tahap_5_masuk.sekolah_id 
                                                            AND sisa_kuota.jalur_id = rekap.peringkat_ppdb_tahap_5_masuk.jalur_id 
                                                        ) peringkat_ppdb_tahap_5_masuk_sekolah");
        
        if($exe_22_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk_sekolah ...".PHP_EOL;
        }
        if($exe_22_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_5_masuk_sekolah ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_5_masuk_sekolah ...".PHP_EOL;
        }

        // 23
        $exe_23_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk_sekolah_diambil");
        $exe_23_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *
                                                        INTO rekap.peringkat_ppdb_tahap_5_masuk_sekolah_diambil
                                                        FROM
                                                        (
                                                            SELECT
                                                                ROW_NUMBER
                                                                    () OVER (
                                                                            PARTITION BY peringkat_ppdb_tahap_5_masuk_sekolah.calon_peserta_didik_id
                                                                    ORDER BY
                                                                            peringkat_ppdb_tahap_5_masuk_sekolah.urut_dipilih ASC
                                                                ) urutan_diambil,
                                                                *
                                                            FROM
                                                                rekap.peringkat_ppdb_tahap_5_masuk_sekolah 
                                                            WHERE
                                                                    status_final_tahap_5 = 'MASUK'
                                                        ) peringkat_ppdb_tahap_5_masuk_sekolah_diambil
                                                        WHERE
                                                            peringkat_ppdb_tahap_5_masuk_sekolah_diambil.urutan_diambil = 1");
        
        if($exe_23_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_5_masuk_sekolah_diambil ...".PHP_EOL;
        }
        if($exe_23_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_5_masuk_sekolah_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_5_masuk_sekolah_diambil ...".PHP_EOL;
        }

        // 24
        $exe_24_1 = DB::connection('sqlsrv_2')->statement("INSERT INTO rekap.peringkat_ppdb_tahap_1_2 SELECT
                                                            urutan_diambil,
                                                            calon_peserta_didik_id,
                                                            nama,
                                                            jalur_id,
                                                            nama_jalur,
                                                            no_urut_final,
                                                            status_final,
                                                            sekolah_id,
                                                            sekolah_penerima,
                                                            no_urut_penerimaan,
                                                            kuota_sekolah,
                                                            jarak,
                                                            jarak_km,
                                                            rd,
                                                            urut_dipilih
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_5_masuk_sekolah_diambil");
        if($exe_24_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }

        // 25
        $exe_25_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.sisa_kuota_tahap_5");
        $exe_25_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            SUM ( 1 ) AS jumlah,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) as kuota,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END) - SUM ( 1 ) as sisa
                                                        INTO rekap.sisa_kuota_tahap_5
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_1_2
                                                        JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                                        GROUP BY
                                                            rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                                            rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                                            (CASE 
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                                    WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                                    ELSE 0
                                                            END)");
        
        if($exe_25_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.sisa_kuota_tahap_5 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.sisa_kuota_tahap_5 ...".PHP_EOL;
        }
        if($exe_25_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.sisa_kuota_tahap_5 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.sisa_kuota_tahap_5 ...".PHP_EOL;
        }

        // 24 bersih2 dulu
        $exe_24_1 = DB::connection('sqlsrv_2')->statement("UPDATE rekap.peringkat_ppdb_tahap_1_2 AS v 
                                                        SET no_urut_final = s.urutan 
                                                        FROM
                                                            ( SELECT ROW_NUMBER () OVER ( PARTITION BY sekolah_id, jalur_id ORDER BY no_urut_final ASC ) AS urutan,
                                                            * FROM rekap.peringkat_ppdb_tahap_1_2 ORDER BY sekolah_id, jalur_id ) AS s 
                                                        WHERE
                                                            v.calon_peserta_didik_id = s.calon_peserta_didik_id");
        if($exe_24_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] NORMALISASI NO URUT PENERIMAAN rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] NORMALISASI NO URUT PENERIMAAN rekap.peringkat_ppdb_tahap_1_2  ...".PHP_EOL;
        }
        
        // 24
        $exe_24_1 = DB::connection('sqlsrv_2')->statement("DELETE from rekap.peringkat_ppdb_tahap_1_2 where status_final = 'TERDEGRADASI'");
        if($exe_24_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] HAPUS RESIDU DEGRADASI rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] HAPUS RESIDU DEGRADASI rekap.peringkat_ppdb_tahap_1_2  ...".PHP_EOL;
        }


        // query tambahan
        $exe_99_0 = DB::connection('sqlsrv_2')->statement("DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6");
        if($exe_99_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6 perbaikan...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6 perbaikan...".PHP_EOL;
        }
        
        $exe_99_1 = DB::connection('sqlsrv_2')->statement("SELECT
            calon.calon_peserta_didik_id,
            calon.nama,
            peringkatan.jalur_id,
            peringkatan.nama_jalur,	
            peringkatan.no_urut_final,
            (case when peringkatan.no_urut_final <= peringkatan.kuota_sekolah THEN 'MASUK' else 'TERDEGRADASI' END) as status_final,
            peringkatan.sekolah_id,
            peringkatan.sekolah_penerima,
            peringkatan.no_urut_penerimaan,
            peringkatan.kuota_sekolah,
            peringkatan.jarak,
            peringkatan.jarak_km,
            peringkatan.rd,
            peringkatan.urut_dipilih,
            now() as tanggal_rekap
        INTO rekap.peringkat_ppdb_tahap_6
        FROM
            ppdb.calon_peserta_didik calon
            LEFT JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calon.calon_peserta_didik_id AND pd_diterima.urutan = 1 
            LEFT JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calon.calon_peserta_didik_id
            LEFT JOIN (
            SELECT finals.* FROM (
            SELECT 
                ROW_NUMBER
                () OVER (
                        PARTITION BY aa.sekolah_id, aa.jalur_id
                ORDER BY
                    aa.no_urut_penerimaan ASC
                ) no_urut_final,
                * 
            FROM (
            SELECT
                calon.calon_peserta_didik_id,
                calon.nama,
                (CASE 
                            WHEN ranking_zonasi.sekolah_id is not null then ranking_zonasi.sekolah_id
                            WHEN ranking_affirmasi.sekolah_id is not null then ranking_affirmasi.sekolah_id
                            WHEN ranking_pindah.sekolah_id is not null then ranking_pindah.sekolah_id
                            WHEN ranking_minat.sekolah_id is not null then ranking_minat.sekolah_id
                            WHEN ranking_tahfidz.sekolah_id is not null then ranking_tahfidz.sekolah_id
                            ELSE NULL
                END) as sekolah_id,
                (CASE 
                            WHEN ranking_zonasi.nama_sekolah is not null then ranking_zonasi.nama_sekolah
                            WHEN ranking_affirmasi.nama_sekolah is not null then ranking_affirmasi.nama_sekolah
                            WHEN ranking_pindah.nama_sekolah is not null then ranking_pindah.nama_sekolah
                            WHEN ranking_minat.nama_sekolah is not null then ranking_minat.nama_sekolah
                            WHEN ranking_tahfidz.nama_sekolah is not null then ranking_tahfidz.nama_sekolah
                            ELSE NULL
                END) as sekolah_penerima,
                (CASE 
                            WHEN ranking_zonasi.ranking_sekolah is not null then ranking_zonasi.ranking_sekolah
                            WHEN ranking_affirmasi.ranking_sekolah is not null then ranking_affirmasi.ranking_sekolah
                            WHEN ranking_pindah.ranking_sekolah is not null then ranking_pindah.ranking_sekolah
                            WHEN ranking_minat.ranking_sekolah is not null then ranking_minat.ranking_sekolah
                            WHEN ranking_tahfidz.ranking_sekolah is not null then ranking_tahfidz.ranking_sekolah
                            ELSE NULL
                END) as no_urut_penerimaan,
                (CASE 
                            WHEN ranking_zonasi.kuota is not null then ranking_zonasi.kuota
                            WHEN ranking_affirmasi.kuota is not null then ranking_affirmasi.kuota
                            WHEN ranking_pindah.kuota is not null then ranking_pindah.kuota
                            WHEN ranking_minat.kuota is not null then ranking_minat.kuota
                            WHEN ranking_tahfidz.kuota is not null then ranking_tahfidz.kuota
                            ELSE NULL
                END) as kuota_sekolah,
                (CASE 
                            WHEN ranking_zonasi.nama_jalur is not null then ranking_zonasi.nama_jalur
                            WHEN ranking_affirmasi.nama_jalur is not null then ranking_affirmasi.nama_jalur
                            WHEN ranking_pindah.nama_jalur is not null then ranking_pindah.nama_jalur
                            WHEN ranking_minat.nama_jalur is not null then ranking_minat.nama_jalur
                            WHEN ranking_tahfidz.nama_jalur is not null then ranking_tahfidz.nama_jalur
                            ELSE NULL
                END) as nama_jalur,
                (CASE 
                            WHEN ranking_zonasi.jalur_id is not null then ranking_zonasi.jalur_id
                            WHEN ranking_affirmasi.jalur_id is not null then ranking_affirmasi.jalur_id
                            WHEN ranking_pindah.jalur_id is not null then ranking_pindah.jalur_id
                            WHEN ranking_minat.jalur_id is not null then ranking_minat.jalur_id
                            WHEN ranking_tahfidz.jalur_id is not null then ranking_tahfidz.jalur_id
                            ELSE NULL
                END) as jalur_id,
                (CASE 
                            WHEN ranking_zonasi.jarak is not null then ranking_zonasi.jarak
                            WHEN ranking_affirmasi.jarak is not null then ranking_affirmasi.jarak
                            WHEN ranking_pindah.jarak is not null then ranking_pindah.jarak
                            WHEN ranking_minat.jarak is not null then ranking_minat.jarak
                            WHEN ranking_tahfidz.jarak is not null then ranking_tahfidz.jarak
                            ELSE NULL 
                END) as jarak,
                (CASE 
                            WHEN ranking_zonasi.jarak_km is not null then ranking_zonasi.jarak_km
                            WHEN ranking_affirmasi.jarak_km is not null then ranking_affirmasi.jarak_km
                            WHEN ranking_pindah.jarak_km is not null then ranking_pindah.jarak_km
                            WHEN ranking_minat.jarak_km is not null then ranking_minat.jarak_km
                            WHEN ranking_tahfidz.jarak_km is not null then ranking_tahfidz.jarak_km
                            ELSE NULL
                END) as jarak_km,
                ranking_zonasi.rd,
                ranking_zonasi.urut_dipilih
            FROM
                ppdb.calon_peserta_didik calon
                LEFT JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calon.calon_peserta_didik_id AND pd_diterima.urutan = 1
                LEFT JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calon.calon_peserta_didik_id
                
                -- join untuk zonasi
                LEFT JOIN (
                    SELECT
                        ROW_NUMBER
                        () OVER (
                                PARTITION BY ranking_sekolah.calon_peserta_didik_id
                        ORDER BY
                                ranking_diri.ranking_diri ASC
                        ) urut_dipilih,
                        ranking_diri.ranking_diri as ranking_diri,
                        (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                        jalur.nama as nama_jalur,
                        ranking_sekolah.*,
                        ranking_diri.ranking_diri as rd
                    FROM
                        ppdb.pilihan_sekolah
                    JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                    JOIN (
                        SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                            ORDER BY
                                    -- pilihan.urut_pilihan ASC,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) ASC
                            ) AS ranking_sekolah,
                            COALESCE(cari_kuota_sisa.sisa,0) as kuota,
                            pilihan.urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                        FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            LEFT JOIN (
                                SELECT sekolah_id, jalur_id, (total - terima) AS sisa FROM (
                                    SELECT 
                                        rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                        rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                        SUM( 1 ) as terima,
                                        max(CASE 
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                ELSE 0
                                        END) as total
                                    FROM
                                        rekap.peringkat_ppdb_tahap_1_2 
                                        join ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                    group by rekap.peringkat_ppdb_tahap_1_2.sekolah_id, jalur_id
                                ) as kuota
                            ) cari_kuota_sisa on cari_kuota_sisa.sekolah_id = pilihan.sekolah_id and cari_kuota_sisa.jalur_id = pilihan.jalur_id
                        WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id = '0400'
                            AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                    ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.calon_peserta_didik_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC
                            )	AS ranking_diri,
                            pilihan.urut_pilihan as urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id = '0400'
                    ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    WHERE
                            ppdb.pilihan_sekolah.soft_delete = 0
                    -- AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                    ) ranking_zonasi on ranking_zonasi.calon_peserta_didik_id = calon.calon_peserta_didik_id 
        
                    -- join untuk affirmasi
                    LEFT JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY ranking_sekolah.calon_peserta_didik_id
                            ORDER BY
                                    ranking_diri.ranking_diri ASC
                            ) urut_dipilih,
                            ranking_diri.ranking_diri as ranking_diri,
                            (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                            jalur.nama as nama_jalur,
                            ranking_sekolah.*
                    FROM
                            ppdb.pilihan_sekolah
                    JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) ASC
                            ) AS ranking_sekolah,
                            COALESCE(cari_kuota_sisa.sisa,0) as kuota,
                            pilihan.urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 		
                            LEFT JOIN (
                                SELECT sekolah_id, jalur_id, (total - terima) AS sisa FROM (
                                    SELECT 
                                        rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                        rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                        SUM( 1 ) as terima,
                                        max(CASE 
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                                WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                                ELSE 0
                                        END) as total
                                    FROM
                                        rekap.peringkat_ppdb_tahap_1_2 
                                        join ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                    group by rekap.peringkat_ppdb_tahap_1_2.sekolah_id, jalur_id
                                ) as kuota
                            ) cari_kuota_sisa on cari_kuota_sisa.sekolah_id = pilihan.sekolah_id and cari_kuota_sisa.jalur_id = pilihan.jalur_id
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id = '0100'
                            AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                    ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.calon_peserta_didik_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC
                            )	AS ranking_diri,
                            pilihan.urut_pilihan as urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id = '0100'
                    ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    WHERE
                            ppdb.pilihan_sekolah.soft_delete = 0
                            AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                    ) ranking_affirmasi on ranking_affirmasi.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                    AND ranking_affirmasi.urut_dipilih = 1
        
                    -- join untuk perpindahan orang tua
                    LEFT JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY ranking_sekolah.calon_peserta_didik_id
                            ORDER BY
                                    ranking_diri.ranking_diri ASC
                            ) urut_dipilih,
                            ranking_diri.ranking_diri as ranking_diri,
                            (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                            jalur.nama as nama_jalur,
                            ranking_sekolah.*
                    FROM
                            ppdb.pilihan_sekolah
                    JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) ASC
                            ) AS ranking_sekolah,
                            COALESCE(cari_kuota_sisa.sisa,0) as kuota,
                            pilihan.urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 		
                            LEFT JOIN (
                            SELECT sekolah_id, jalur_id, (total - terima) AS sisa FROM (
                                SELECT 
                                    rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                    rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                    SUM( 1 ) as terima,
                                    max(CASE 
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as total
                                FROM
                                    rekap.peringkat_ppdb_tahap_1_2 
                                    join ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                group by rekap.peringkat_ppdb_tahap_1_2.sekolah_id, jalur_id
                                ) as kuota
                            ) cari_kuota_sisa on cari_kuota_sisa.sekolah_id = pilihan.sekolah_id and cari_kuota_sisa.jalur_id = pilihan.jalur_id
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id = '0200'
                            AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                    ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.calon_peserta_didik_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC
                            )	AS ranking_diri,
                            pilihan.urut_pilihan as urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id = '0200'
                    ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    WHERE
                            ppdb.pilihan_sekolah.soft_delete = 0
                            AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                    ) ranking_pindah on ranking_pindah.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                    AND ranking_pindah.urut_dipilih = 1
        
                    -- join untuk minat bakat
                    LEFT JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY ranking_sekolah.calon_peserta_didik_id
                            ORDER BY
                                    ranking_diri.ranking_diri ASC
                            ) urut_dipilih,
                            ranking_diri.ranking_diri as ranking_diri,
                            (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                            jalur.nama as nama_jalur,
                            ranking_sekolah.*
                    FROM
                            ppdb.pilihan_sekolah
                    JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) ASC
                            ) AS ranking_sekolah,
                            COALESCE(cari_kuota_sisa.sisa,0) as kuota,
                            pilihan.urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            LEFT JOIN (
                            SELECT sekolah_id, jalur_id, (total - terima) AS sisa FROM (
                                SELECT 
                                    rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                    rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                    SUM( 1 ) as terima,
                                    max(CASE 
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as total
                                FROM
                                    rekap.peringkat_ppdb_tahap_1_2 
                                    join ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                group by rekap.peringkat_ppdb_tahap_1_2.sekolah_id, jalur_id
                                ) as kuota
                            ) cari_kuota_sisa on cari_kuota_sisa.sekolah_id = pilihan.sekolah_id and cari_kuota_sisa.jalur_id = pilihan.jalur_id
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id IN ('0300')
                            AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                    ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.calon_peserta_didik_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC
                            )	AS ranking_diri,
                            pilihan.urut_pilihan as urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	 	
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id IN ('0300')
                    ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    WHERE
                            ppdb.pilihan_sekolah.soft_delete = 0
                        AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                    ) ranking_minat on ranking_minat.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                    AND ranking_minat.urut_dipilih = 1
        
                    -- join untuk tahfidz
                    LEFT JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY ranking_sekolah.calon_peserta_didik_id
                            ORDER BY
                                    ranking_diri.ranking_diri ASC
                            ) urut_dipilih,
                            ranking_diri.ranking_diri as ranking_diri,
                            (CASE WHEN ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota THEN 'Masuk Ranking' ELSE 'Tidak Masuk Ranking' END) AS status,
                            jalur.nama as nama_jalur,
                            ranking_sekolah.*
                    FROM
                            ppdb.pilihan_sekolah
                    JOIN ref.jalur jalur on jalur.jalur_id = ppdb.pilihan_sekolah.jalur_id	
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.sekolah_id, pilihan.jalur_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC,
                                    ppdb.calculate_distance (
                                            ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                            ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                            'Meter' 
                                    ) ASC
                            ) AS ranking_sekolah,
                            COALESCE(cari_kuota_sisa.sisa,0) as kuota,
                            pilihan.urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id 	
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	
                            LEFT JOIN (
                            SELECT sekolah_id, jalur_id, (total - terima) AS sisa FROM (
                                SELECT 
                                    rekap.peringkat_ppdb_tahap_1_2.sekolah_id,
                                    rekap.peringkat_ppdb_tahap_1_2.jalur_id,
                                    SUM( 1 ) as terima,
                                    max(CASE 
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0100' THEN kuota.kuota_0100
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0200' THEN kuota.kuota_0200
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0300') THEN kuota.kuota_0300
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id IN ('0500') THEN kuota.kuota_0500
                                            WHEN rekap.peringkat_ppdb_tahap_1_2.jalur_id = '0400' THEN kuota.kuota_0400
                                            ELSE 0
                                    END) as total
                                FROM
                                    rekap.peringkat_ppdb_tahap_1_2 
                                    join ppdb.kuota_sekolah kuota on kuota.sekolah_id = rekap.peringkat_ppdb_tahap_1_2.sekolah_id
                                group by rekap.peringkat_ppdb_tahap_1_2.sekolah_id, jalur_id
                                ) as kuota
                            ) cari_kuota_sisa on cari_kuota_sisa.sekolah_id = pilihan.sekolah_id and cari_kuota_sisa.jalur_id = pilihan.jalur_id
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id IN ('0500')
                            AND pilihan.calon_peserta_didik_id not in (select calon_peserta_didik_id from rekap.peringkat_ppdb_tahap_1_2)
                    ) ranking_sekolah on ranking_sekolah.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    JOIN (
                    SELECT
                            ROW_NUMBER
                            () OVER (
                                    PARTITION BY pilihan.calon_peserta_didik_id
                            ORDER BY
                                    pilihan.urut_pilihan ASC
                            )	AS ranking_diri,
                            pilihan.urut_pilihan as urut_pilihan,
                            calons.nama,
                            sekolah.nama AS nama_sekolah,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'Meter' 
                            ) AS jarak,
                            ppdb.calculate_distance (
                                    ( CASE WHEN LENGTH ( calons.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( calons.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calons.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
                                    ( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
                                    'K' 
                            ) AS jarak_km,
                            pilihan.* 
                    FROM
                            ppdb.pilihan_sekolah pilihan
                            JOIN ppdb.calon_peserta_didik calons ON calons.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
                            JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = pilihan.sekolah_id
                            JOIN ppdb.kuota_sekolah kuota on kuota.sekolah_id = sekolah.sekolah_id
                            JOIN ( SELECT ROW_NUMBER () OVER ( PARTITION BY peserta_didik_id ORDER BY create_date DESC) as urutan, * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id AND pd_diterima.urutan = 1
                            -- JOIN ( SELECT * FROM ppdb.peserta_didik_diterima WHERE soft_delete = 0 AND status_terima = 1 ) pd_diterima ON pd_diterima.peserta_didik_id = calons.calon_peserta_didik_id 
                            JOIN ( SELECT * From ppdb.konfirmasi_pendaftaran where soft_delete = 0 AND status = 1 ) pd_konfirmasi on pd_konfirmasi.calon_peserta_didik_id = calons.calon_peserta_didik_id 	 	
                    WHERE
                            pilihan.soft_delete = 0 
                            AND calons.soft_delete = 0 
                            AND pilihan.jalur_id IN ('0500')
                    ) ranking_diri on ranking_diri.calon_peserta_didik_id = ppdb.pilihan_sekolah.calon_peserta_didik_id 
                    AND ranking_diri.sekolah_id = ppdb.pilihan_sekolah.sekolah_id
                    WHERE
                            ppdb.pilihan_sekolah.soft_delete = 0
                        AND ranking_sekolah.ranking_sekolah <= ranking_sekolah.kuota
                    ) ranking_tahfidz on ranking_tahfidz.calon_peserta_didik_id = calon.calon_peserta_didik_id 
                    AND ranking_tahfidz.urut_dipilih = 1
            WHERE
                calon.soft_delete = 0
            AND pd_konfirmasi.calon_peserta_didik_id IS NOT NULL
            AND pd_diterima.peserta_didik_id IS NOT NULL
            AND pd_diterima.status_terima = 1
            ) aa
            ) finals
            ) peringkatan on peringkatan.calon_peserta_didik_id = calon.calon_peserta_didik_id
            WHERE
                calon.soft_delete = 0
                AND pd_konfirmasi.calon_peserta_didik_id IS NOT NULL
                AND peringkatan.no_urut_final <= peringkatan.kuota_sekolah
            ORDER BY 
                peringkatan.jalur_id, 
                peringkatan.urut_dipilih");
        
        if($exe_99_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6 perbaikan...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6 perbaikan...".PHP_EOL;
        }

        // 1
        $exe_1_0 = DB::connection('sqlsrv_2')->statement("DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6_masuk");
        $exe_1_1 = DB::connection('sqlsrv_2')->statement("SELECT
                                                            *
                                                        INTO rekap.peringkat_ppdb_tahap_6_masuk
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_6 
                                                        WHERE 
                                                            status_final = 'MASUK'");
        if($exe_1_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6_masuk ...".PHP_EOL;
        }
        if($exe_1_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6_masuk ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6_masuk ...".PHP_EOL;
        }

        // 2
        $exe_2_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_6_degradasi");
        $exe_2_1 = DB::connection('sqlsrv_2')->statement("
            with cte as (
            SELECT
                    *
            FROM
                    rekap.peringkat_ppdb_tahap_6 
            WHERE 
                    status_final = 'TERDEGRADASI'
                and sekolah_id is not null
                    UNION
            SELECT 
                rekap.peringkat_ppdb_tahap_6.calon_peserta_didik_id,
                rekap.peringkat_ppdb_tahap_6.nama,
                pilihan.jalur_id,
                jalur.nama as nama_jalur,
                null as no_urut_final,
                rekap.peringkat_ppdb_tahap_6.status_final,
                pilihan.sekolah_id,
                sekolah.nama as sekolah_penerima,
                null as no_urut_penerimaan,
                null as kuota_sekolah,
                null as jarak,
                null as jarak_km,
                null as rd,
                pilihan.urut_pilihan as urut_dipilih,
                rekap.peringkat_ppdb_tahap_6.tanggal_rekap
            FROM
                rekap.peringkat_ppdb_tahap_6
            JOIN ppdb.pilihan_sekolah pilihan on pilihan.calon_peserta_didik_id = rekap.peringkat_ppdb_tahap_6.calon_peserta_didik_id
            JOIN ref.jalur jalur on jalur.jalur_id = pilihan.jalur_id
            JOIN ppdb.sekolah sekolah on sekolah.sekolah_id = pilihan.sekolah_id
            WHERE
                status_final = 'TERDEGRADASI'
            and rekap.peringkat_ppdb_tahap_6.sekolah_id is null
            )
            select 
                * 
            INTO rekap.peringkat_ppdb_tahap_6_degradasi	
            from 
                cte");
        if($exe_2_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6_degradasi ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6_degradasi ...".PHP_EOL;
        }
        if($exe_2_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6_degradasi ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6_degradasi ...".PHP_EOL;
        }

        // 3
        $exe_3_0 = DB::connection('sqlsrv_2')->statement("DROP TABLE IF EXISTS rekap.peringkat_ppdb_tahap_6_masuk_diambil");
        $exe_3_1 = DB::connection('sqlsrv_2')->statement("SELECT 
                                                            peringkat_ppdb_tahap_6_masuk.*
                                                        INTO rekap.peringkat_ppdb_tahap_6_masuk_diambil
                                                        FROM (
                                                            SELECT
                                                                ROW_NUMBER
                                                                    () OVER (
                                                                            PARTITION BY peringkat_ppdb_tahap_6_masuk.calon_peserta_didik_id
                                                                    ORDER BY
                                                                            peringkat_ppdb_tahap_6_masuk.urut_dipilih ASC
                                                                ) urutan_diambil,
                                                                * 
                                                            FROM
                                                                rekap.peringkat_ppdb_tahap_6_masuk 
                                                        ) peringkat_ppdb_tahap_6_masuk
                                                        where peringkat_ppdb_tahap_6_masuk.urutan_diambil = 1");
        if($exe_3_0){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6_masuk_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] DROP table IF EXISTS rekap.peringkat_ppdb_tahap_6_masuk_diambil ...".PHP_EOL;
        }
        if($exe_3_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6_masuk_diambil ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_6_masuk_diambil ...".PHP_EOL;
        }

        // 24
        $exe_24_1 = DB::connection('sqlsrv_2')->statement("INSERT INTO rekap.peringkat_ppdb_tahap_1_2 SELECT
                                                            urutan_diambil,
                                                            calon_peserta_didik_id,
                                                            nama,
                                                            jalur_id,
                                                            nama_jalur,
                                                            no_urut_final,
                                                            status_final,
                                                            sekolah_id,
                                                            sekolah_penerima,
                                                            no_urut_penerimaan,
                                                            kuota_sekolah,
                                                            jarak,
                                                            jarak_km,
                                                            rd,
                                                            urut_dipilih
                                                        FROM
                                                            rekap.peringkat_ppdb_tahap_6_masuk_diambil");
        if($exe_24_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 dari tahap 6...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] INSERT DATA INTO rekap.peringkat_ppdb_tahap_1_2 dari tahap 6 ...".PHP_EOL;
        }
        
        // 24
        $exe_24_1 = DB::connection('sqlsrv_2')->statement("UPDATE rekap.peringkat_ppdb_tahap_1_2 AS v 
                                                        SET no_urut_final = s.urutan 
                                                        FROM
                                                            ( SELECT ROW_NUMBER () OVER ( PARTITION BY sekolah_id, jalur_id ORDER BY no_urut_final ASC ) AS urutan,
                                                            * FROM rekap.peringkat_ppdb_tahap_1_2 ORDER BY sekolah_id, jalur_id ) AS s 
                                                        WHERE
                                                            v.calon_peserta_didik_id = s.calon_peserta_didik_id");
        if($exe_24_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] NORMALISASI NO URUT PENERIMAAN rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] NORMALISASI NO URUT PENERIMAAN rekap.peringkat_ppdb_tahap_1_2  ...".PHP_EOL;
        }
        
        // 24
        $exe_24_1 = DB::connection('sqlsrv_2')->statement("DELETE from rekap.peringkat_ppdb_tahap_1_2 where status_final = 'TERDEGRADASI'");
        if($exe_24_1){
            echo "[".date('Y-m-d H:i:s')."] [BERHASIL] HAPUS RESIDU DEGRADASI rekap.peringkat_ppdb_tahap_1_2 ...".PHP_EOL;
        }else{
            echo "[".date('Y-m-d H:i:s')."] [GAGAL] HAPUS RESIDU DEGRADASI rekap.peringkat_ppdb_tahap_1_2  ...".PHP_EOL;
        }
        
    }
}
