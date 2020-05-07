<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class PilihsekolahController extends Controller
{
    public function index(Request $request)
    {
    	$limit = $request->limit ? $request->limit : 10;
        $offset = $request->page ? ($request->page * $limit) : 0;

        $count_all = DB::connection('sqlsrv_2')->table('ppdb.pilihan_sekolah')->where('soft_delete', 0);
        $sekolahs = DB::connection('sqlsrv_2')
        	->table('ppdb.pilihan_sekolah AS pilihan_sekolah')
        	->select(
        		'pilihan_sekolah.*',
        		'sekolah.nama AS nama_sekolah'
        	)
        	->leftJoin('ppdb.sekolah AS sekolah', 'pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
        	->where('pilihan_sekolah.soft_delete', 0)
        	->limit($limit)
        	->offset($offset)
        	->orderBy('pilihan_sekolah.create_date', 'DESC');

        $count_all = $count_all->count();
        $sekolahs = $sekolahs->get();

        return response(
            [
                'data' => $sekolahs,
                'count' => count($sekolahs),
                'count_all' => $count_all
            ],
            200
        );
    }

    public function store(Request $request)
    {
        $sekolah_id = $request->sekolah_id;
        $calon_pd_id = $request->calon_peserta_didik_id;
    }
}
