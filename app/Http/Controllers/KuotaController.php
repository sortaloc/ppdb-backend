<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DB;

class KuotaController extends Controller
{
    public function sekolah(Request $request)
    {
    	$searchText = $request->searchText ? $request->searchText : '';
    	$start = $request->start ? $request->start : 0;
    	$sekolah_id = $request->sekolah_id ? $request->sekolah_id : '';

    	$sekolah = DB::connection('sqlsrv_2')
    		->table('ppdb.sekolah AS sekolah')
    		->select(
    			'sekolah.*',
    			'kuota_sekolah.kuota',
    			'kuota_sekolah.kuota_0100',
    			'kuota_sekolah.kuota_0200',
    			'kuota_sekolah.kuota_0300',
    			'kuota_sekolah.kuota_0400',
    			'kuota_sekolah.kuota_0500'
    		)
    		->leftJoin('ppdb.kuota_sekolah AS kuota_sekolah', 'sekolah.sekolah_id', '=', 'kuota_sekolah.sekolah_id')
    		->where(DB::raw('LEFT(kode_wilayah, 4)'), substr($request->kode_wilayah, 0, 4))
    		->orderBy('npsn', 'ASC');

    	if($request->sekolah_id){
    		$sekolah->where('sekolah.sekolah_id', $sekolah_id)->limit(1);
    	}

    	if($searchText !== ""){
    		if(is_numeric($searchText)){
    			$sekolah->where('sekolah.npsn', 'like', "%{$searchText}%");
    		}else{
    			$sekolah->where('sekolah.nama', 'like', "%{$searchText}%");
    		}
    	}

    	$count = $sekolah->count();
    	$sekolah = $sekolah->limit(20)->offset($start)->get();

    	return Response(['rows' => $sekolah, 'count' => count($sekolah), 'countAll' => $count], 200);
    }

    public function save(Request $request)
    {
		$sekolah_id = $request->sekolah_id;

		$kuota = [
			'kuota' => $request->kuota,
			'kuota_0100' => $request->kuota_0100,
			'kuota_0200' => $request->kuota_0200,
			'kuota_0300' => $request->kuota_0300,
			'kuota_0400' => $request->kuota_0400,
			'kuota_0500' => $request->kuota_0500
		];

		$kuota_sekolah = DB::connection('sqlsrv_2')
			->table('ppdb.kuota_sekolah')
			->where('sekolah_id', $sekolah_id);

		if($kuota_sekolah->count() == 1){
			$kuota['last_update'] = date("Y-m-d H:i:s");
			// return "update";
			$kuota_sekolah = DB::connection('sqlsrv_2')->table('ppdb.kuota_sekolah')->where('sekolah_id', $sekolah_id)->update($kuota);
		}else{
			$uuid   = Str::uuid();

			$kuota['kuota_sekolah_id'] = $uuid;
			$kuota['sekolah_id'] = $sekolah_id;
			$kuota['periode_kegiatan_id'] = '2020';
			$kuota['create_date'] = date("Y-m-d H:i:s");
			$kuota['last_update'] = date("Y-m-d H:i:s");
			// $kuota['soft_delete'] = 0;
			$kuota['rombel_periode_lalu'] = '1';
			// return "create";

			$kuota_sekolah = DB::connection('sqlsrv_2')->table('ppdb.kuota_sekolah')->insert($kuota);
		}

		return Response(['status' => 'success']);
    }
}
