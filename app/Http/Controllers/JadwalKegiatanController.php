<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\JadwalKegiatan AS Jadwal;
use Illuminate\Support\Str;
use DB;

class JadwalKegiatanController extends Controller
{
    public function index(Request $request)
    {
    	$limit = $request->limit ? $request->limit : 10;
	    $offset = $request->page ? ($request->page * $limit) : 0;
	    $searchText = $request->searchText ? $request->searchText : '';

	    $jadwal = Jadwal::select(
	    		'jadwal_kegiatan.*',
	    		'jalur.nama As jalur',
	    		'wilayah.nama AS nama_wilayah'
	    	)
		    ->where('jadwal_kegiatan.soft_delete', 0)
		    ->leftJoin('ref.jalur AS jalur', 'jadwal_kegiatan.jalur_id', '=', 'jalur.jalur_id')
		    ->leftJoin('ref.mst_wilayah AS wilayah', 'jadwal_kegiatan.kode_wilayah', '=', 'wilayah.kode_wilayah');

	    $jadwal_limit = $jadwal->limit($limit)
	    	->offset($offset)
	    	->get();

	    return response(
	        [
	            'rows' => $jadwal_limit,
	            'count' => count($jadwal_limit),
	            'countAll' => $jadwal->count()
	        ],
	        200
	    );
    }

    public function store(Request $request)
    {
    	$jadwal_kegiatan_id = $request->jadwal_kegiatan_id == "" ? null : $request->jadwal_kegiatan_id;
    	$data = $request->all();

    	if($jadwal_kegiatan_id != null){
    		$cek = Jadwal::where('jadwal_kegiatan_id', $jadwal_kegiatan_id)->count();
    	}else{
    		$cek = 0;
    	}

    	if($cek == 1){
    		// Update
    		$jadwal = Jadwal::find($jadwal_kegiatan_id);
			$jadwal->periode_kegiatan_id = $request->periode_kegiatan_id;
			$jadwal->nama = $request->nama;
			$jadwal->kode_wilayah = $request->kode_wilayah;
			$jadwal->tanggal_mulai = $request->tanggal_mulai;
			$jadwal->tanggal_selesai = $request->tanggal_selesai;
			$jadwal->pengguna_id = $request->pengguna_id;
			$jadwal->jalur_id = $request->jalur_id;
			$jadwal->save();

    	}else{
    		// Create
    		$uuid   = Str::uuid();
    		$data['jadwal_kegiatan_id'] = $uuid;
    		$jadwal = Jadwal::create($data);
    	}

    	return response([ 'rows' => $jadwal ], 201);
    }

    public function destroy(Request $request, $id)
    {
    	$jadwal = Jadwal::where('jadwal_kegiatan_id', $id)->update(['soft_delete' => 1]);
    	return response([ 'rows' => $jadwal ], 201);
    }

    public function beranda(Request $request)
    {
    	$jadwal = Jadwal::where('jadwal_kegiatan.soft_delete', 0)
    		->orderBy('jadwal_kegiatan.tanggal_mulai', 'ASC')
    		->get();

    	return response(
	        [
	            'rows' => $jadwal,
	            'count' => count($jadwal),
	        ],
	        200
	    );
    }
}
