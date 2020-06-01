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
            ->limit(4)
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

        $jalur_chart['label'] = [];
        $jalur_chart['data'] = [];

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

            $jalur_chart['label'][$i] = $key->nama;
            $jalur_chart['data'][$i] = $key->total_semua;

            $i++;
        }

        // END Jalur

        // Status Pendaftaram
        $Status_terima['sd'] = 0;
        $Status_terima['smp'] = 0;
        // END Status Pendaftaran

        // Widget
        $kuota = DB::connection('sqlsrv_2')->table('ppdb.kuota_sekolah')->select(DB::raw('SUM(kuota) AS jumlah_kuota'))->get();
        $kuota_terima = 0;
        $kuota_pendaftar = DB::connection('sqlsrv_2')->table('ppdb.pilihan_sekolah')->where('soft_delete', 0)->count();

        $kuota_chart = [
            'label' => ['Kuota', 'Pendaftar', 'Terima'],
            'data' => [ $kuota[0]->jumlah_kuota, $kuota_pendaftar, $kuota_terima ]
        ];

        $pilihan_sekolah = DB::connection('sqlsrv_2')
            ->table('ppdb.pilihan_sekolah AS pilihan_sekolah')
            ->select(
                'urut_pilihan',
                DB::raw('COUNT(urut_pilihan) AS count')
            )
            ->join('ppdb.sekolah', 'pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
            ->where(DB::raw('LEFT(sekolah.kode_wilayah, 4)'), substr($kode_wilayah, 0, 4))
            ->groupBy('pilihan_sekolah.urut_pilihan')
            ->get();

        $pilihan_sekolah_chart['label'] = [];
        $pilihan_sekolah_chart['data'] = [];

        $i = 0;
        foreach ($pilihan_sekolah as $key) {
            $pilihan_sekolah_chart['label'][$i] = "Pilihan " . $key->urut_pilihan;
            $pilihan_sekolah_chart['data'][$i] = intval($key->count);

            $i++;
        }

        // END Widget

        return Response([
            'jadwal' => $jadwal,
            'jalur' => [
                'rows' => $jalur,
                'count_sd' => $ttl_sd,
                'count_smp' => $ttl_smp,
            ],
            'jalur_chart' => $jalur_chart,
            'status_terima' => $Status_terima,
            'kuota' => [
                'kuota' => $kuota[0]->jumlah_kuota,
                'terima' => $kuota_terima,
                'pendaftar' => $kuota_pendaftar,
            ],
            'kuota_chart' => $kuota_chart,
            'pilihan_sekolah' => $pilihan_sekolah,
            'pilihan_sekolah_chart' => $pilihan_sekolah_chart
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
            ->leftjoin('ppdb.pilihan_sekolah AS pilihan_sekolah', 'pilihan_sekolah.sekolah_id', '=', 'pilihan_sekolah.sekolah_id')
            ->where('pilihan_sekolah.urut_pilihan', 1)
            ->where('sekolah.sekolah_id', $sekolah_id)
            ->first();

        // kuota jalur
        $jalur = DB::connection('sqlsrv_2')
            ->table('ref.jalur AS jalur')
            ->select(
                'jalur_id',
                'nama',
                DB::raw("(SELECT COUNT(calon_peserta_didik_id) FROM ppdb.pilihan_sekolah WHERE sekolah_id = '{$sekolah_id}' AND jalur_id = jalur.jalur_id AND urut_pilihan = '1') AS pendaftar")
            )
            ->orderBy('jalur_id')
            ->where('level_jalur', 1)
            ->get();

        $kuota_pendaftar = [];

        $i = 0;
        foreach ($jalur as $key) {
            $kuota_pendaftar['kuota_'.$key->jalur_id] = $key;
            $kuota_pendaftar['kuota_'.$key->jalur_id]->terima = 0;
        }
        //END Kuota Jalur

        // Pilihan
        $pilihan_sekolah = DB::connection('sqlsrv_2')
            ->table('ppdb.pilihan_sekolah AS pilihan_sekolah')
            ->select(
                'pilihan_sekolah.urut_pilihan',
                DB::raw('COUNT(pilihan_sekolah.urut_pilihan) AS count')
            )
            ->where('pilihan_sekolah.sekolah_id', $sekolah_id)
            ->groupBy(
                'pilihan_sekolah.urut_pilihan',
                'pilihan_sekolah.sekolah_id'
            )
            ->orderBy(
                'pilihan_sekolah.urut_pilihan', 'ASC'
            )
            ->get();

        //END Pilihan

        return Response([
            'sekolah' => $sekolah,
            'pendaftar' => $kuota_pendaftar,
            'pilihan_sekolah' => $pilihan_sekolah,
        ], 200);
    }
}
