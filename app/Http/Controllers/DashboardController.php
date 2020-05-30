<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class DashboardController extends Controller
{
    public function beranda_dinas(Request $request)
    {
        $kode_wilayah = $request->kode_wilayah ? $request->kode_wilayah : '052100';

        // Jadwal
        $jadwal = DB::connection('sqlsrv_2')
            ->table('ppdb.jadwal_kegiatan')
            ->select('nama', 'tanggal_mulai')
            ->limit(3)
            ->where('soft_delete', 0)
            ->where('kode_wilayah', $kode_wilayah)
            ->orderBy('create_date', 'DESC')
            ->get();
        // END Jadwal

        // Jalur
        $jalur = DB::connection('sqlsrv_2')
            ->table('ref.jalur AS jalur')
            ->select('jalur.jalur_id', 'jalur.nama', DB::raw('COUNT(pilihan.jalur_id) AS total_semua'))
            ->join('ppdb.pilihan_sekolah AS pilihan', 'jalur.jalur_id', '=', 'pilihan.jalur_id')
            ->join('ppdb.sekolah AS sekolah', 'pilihan.sekolah_id', '=', 'sekolah.sekolah_id')
            ->where('sekolah.soft_delete', 0)
            ->where(DB::raw('LEFT(sekolah.kode_wilayah, 4)'), substr($kode_wilayah, 0, 4))
            ->groupBy(
                'jalur.jalur_id',
                'jalur.nama',
                'pilihan.jalur_id'
            )
            ->orderBy('jalur.jalur_id', 'ASC')
            ->get();

        $ttl_sd = 0;
        $ttl_smp = 0;

        $i = 0;
        foreach ($jalur as $key) {
            $sd = DB::connection('sqlsrv_2')
                ->table('ppdb.pilihan_sekolah')
                ->join('ppdb.sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'ppdb.sekolah.sekolah_id')
                ->where('ppdb.pilihan_sekolah.jalur_id', $key->jalur_id)
                ->where(DB::raw('LEFT(sekolah.kode_wilayah, 4)'), substr($kode_wilayah, 0, 4))
                ->where('ppdb.sekolah.bentuk_pendidikan_id', 5)
                ->count();

            $smp = DB::connection('sqlsrv_2')
                ->table('ppdb.pilihan_sekolah')
                ->join('ppdb.sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'ppdb.sekolah.sekolah_id')
                ->where('ppdb.pilihan_sekolah.jalur_id', $key->jalur_id)
                ->where(DB::raw('LEFT(sekolah.kode_wilayah, 4)'), substr($kode_wilayah, 0, 4))
                ->where('ppdb.sekolah.bentuk_pendidikan_id', 6)
                ->count();

            $jalur[$i]->sd = $sd;
            $jalur[$i]->smp = $smp;

            $ttl_sd = $ttl_sd + $sd;
            $ttl_smp = $ttl_smp + $smp;

            $i++;
        }

        // END Jalur

        // Status Pendaftaram
        $Status_terima['sd'] = DB::connection('sqlsrv_2')
            ->table('ppdb.konfirmasi_pendaftaran')
            ->rightJoin('ppdb.pilihan_sekolah AS pilihan_sekolah', 'ppdb.konfirmasi_pendaftaran.calon_peserta_didik_id', '=', 'pilihan_sekolah.calon_peserta_didik_id')
            ->join('ppdb.sekolah AS sekolah', 'pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
            ->where('ppdb.konfirmasi_pendaftaran.status', 9)
            ->where('ppdb.konfirmasi_pendaftaran.soft_delete', 0)
            ->where('sekolah.bentuk_pendidikan_id', 5)
            ->count();

        $Status_terima['smp'] = DB::connection('sqlsrv_2')
            ->table('ppdb.konfirmasi_pendaftaran')
            ->where('status', 9)
            ->where('soft_delete', 0)
            ->count();
        // END Status Pendaftaran

        // Widget
        $kuota = DB::connection('sqlsrv_2')->table('ppdb.kuota_sekolah')->select(DB::raw('SUM(kuota) AS jumlah_kuota'))->get();
        $kuoto_terima = DB::connection('sqlsrv_2')->table('ppdb.konfirmasi_pendaftaran')->select(DB::raw('SUM(kuota) AS jumlah_kuota'))->where('status', 9)->count();
        $kuoto_pendaftar = DB::connection('sqlsrv_2')->table('ppdb.pilihan_sekolah')->where('soft_delete', 0)->count();

        // END Widget

        return Response([
            'jadwal' => $jadwal,
            'jalur' => [
                'rows' => $jalur,
                'count_sd' => $ttl_sd,
                'count_smp' => $ttl_smp,
            ],
            'status_terima' => $Status_terima,
            'kuota' => [
                'kuota' => $kuota[0]->jumlah_kuota,
                'terima' => $kuoto_terima,
                'pendaftar' => $kuoto_pendaftar
            ]
        ], 200);
    }

    public function beranda_sekolah(Request $request)
    {
        $sekolah_id = $request->sekolah_id ? $request->sekolah_id : '';

        $sekolah = DB::connection('sqlsrv_2')
            ->table('ppdb.sekolah AS sekolah')
            ->select(
                'sekolah.nama',
                'sekolah.npsn',
                'sekolah.kecamatan',
                'sekolah.alamat_jalan',
                'sekolah.lintang',
                'sekolah.bujur',
                'kuota_sekolah.kuota',
                'kuota_sekolah.kuota_0100',
                'kuota_sekolah.kuota_0200',
                'kuota_sekolah.kuota_0300',
                'kuota_sekolah.kuota_0400',
                'kuota_sekolah.kuota_0500'
            )
            ->leftjoin('ppdb.kuota_sekolah AS kuota_sekolah', 'sekolah.sekolah_id', '=', 'kuota_sekolah.sekolah_id')
            ->where('sekolah.sekolah_id', $sekolah_id)
            ->first();

        $pendaftar = DB::connection('sqlsrv_2')
            ->table('ppdb.pilihan_sekolah')
            ->select(
                'jalur_id',
                DB::raw('COUNT(jalur_id)')
            )
            ->where('sekolah_id', $sekolah_id)
            ->where('soft_delete', 0)
            ->groupBy('jalur_id')
            ->get();

        return Response([
            'sekolah' => $sekolah,
            'pendaftar' => $pendaftar
        ], 200);
    }
}
