<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class SekolahController extends Controller
{
    public function index(Request $request)
    {
    	$limit = $request->limit ? $request->limit : 10;
        $offset = $request->page ? ($request->page * $limit) : 0;
        $searchText = $request->searchText ? $request->searchText : '';
        $status_sekolah = $request->status_sekolah ? $request->status_sekolah : '';
        $kode_wilayah = $request->kode_wilayah ? $request->kode_wilayah : null;
        $id_level_wilayah = $request->id_level_wilayah ? $request->id_level_wilayah : 0;

        $count = DB::connection('sqlsrv_2')->table('ppdb.sekolah')->where('soft_delete', 0)
            ->join('ref.bentuk_pendidikan as bp','bp.bentuk_pendidikan_id','=','sekolah.bentuk_pendidikan_id')
            ->join('ref.mst_wilayah as kec','kec.kode_wilayah','=',DB::raw("LEFT(sekolah.kode_wilayah,6)"))
			->join('ref.mst_wilayah as kab','kec.mst_kode_wilayah','=','kab.kode_wilayah')
			->join('ref.mst_wilayah as prop','kab.mst_kode_wilayah','=','prop.kode_wilayah');
        $sekolahs = DB::connection('sqlsrv_2')
            ->table('ppdb.sekolah as sekolah')
            ->join('ref.bentuk_pendidikan as bp','bp.bentuk_pendidikan_id','=','sekolah.bentuk_pendidikan_id')
            ->join('ref.mst_wilayah as kec','kec.kode_wilayah','=',DB::raw("LEFT(sekolah.kode_wilayah,6)"))
			->join('ref.mst_wilayah as kab','kec.mst_kode_wilayah','=','kab.kode_wilayah')
			->join('ref.mst_wilayah as prop','kab.mst_kode_wilayah','=','prop.kode_wilayah')
        	->where('sekolah.soft_delete', 0)
        	->limit($limit)
            ->offset($offset)
            ->select(
                'sekolah.*',
                'bp.nama as bentuk',
                DB::raw("(case when sekolah.status_sekolah = 1 then 'Negeri' else 'Swasta' end) as status")
            )
        	->orderBy('sekolah.nama', 'ASC');

        if($searchText){
        	$count = $count->where('sekolah.npsn', 'ilike', '%'.$searchText.'%')->orWhere('sekolah.nama', 'ilike', '%'.$searchText.'%');
        	$sekolahs = $sekolahs->where('sekolah.npsn', 'ilike', '%'.$searchText.'%')->orWhere('sekolah.nama', 'ilike', '%'.$searchText.'%');
        }
        
        if($status_sekolah){
        	$count = $count->where('sekolah.status_sekolah', '=', $status_sekolah);
        	$sekolahs = $sekolahs->where('sekolah.status_sekolah', '=', $status_sekolah);
        }

        if($kode_wilayah){
			switch ($id_level_wilayah) {
				case 1:
					$count = $count->where('prop.kode_wilayah', "=", $kode_wilayah);
	    			$sekolahs = $sekolahs->where('prop.kode_wilayah', "=", $kode_wilayah);
					break;
				case 2:
					$count = $count->where('kab.kode_wilayah', "=", $kode_wilayah);
					$sekolahs = $sekolahs->where('kab.kode_wilayah', "=", $kode_wilayah);
					break;
				default:
					# code...
					break;
			}
		}

        $count = $count->count();
        $sekolahs = $sekolahs->get();

        return response(
            [
                'rows' => $sekolahs,
                'count' => count($sekolahs),
                'countAll' => $count
            ],
            200
        );
    }
}
