<?php

namespace App\Http\Controllers;

use App\PesertaDidik AS PD;
use DB;
use Illuminate\Http\Request;

class PesertaDidikController extends Controller
{
    public function index(Request $request)
    {
    	$limit = $request->limit ? $request->limit : 10;
	    $offset = $request->page ? ($request->page * $limit) : 0;
	    $searchText = $request->searchText ? $request->searchText : '';
	    $tingkat_akhir_saja = $request->tingkat_akhir_saja ? $request->tingkat_akhir_saja : 1;

	    $count = new PD;
		$pds = PD::limit($limit)
			->join('ref.mst_wilayah as kec','kec.kode_wilayah','=',DB::raw("LEFT(peserta_didik.kode_kec_pd,6)"))
			->join('ref.mst_wilayah as kab','kec.mst_kode_wilayah','=','kab.kode_wilayah')
			->join('ref.mst_wilayah as prop','kab.mst_kode_wilayah','=','prop.kode_wilayah')
			->leftJoin('ppdb.calon_peserta_didik as calon_peserta_didik','calon_peserta_didik.calon_peserta_didik_id','=','peserta_didik.peserta_didik_id')
	    	->offset($offset)
			->orderBy('peserta_didik.nama', 'ASC')
			->select(
				'peserta_didik.*',
				'kec.nama as kecamatan',
				'kab.nama as kabupaten',
				'prop.nama as provinsi',
				'calon_peserta_didik.calon_peserta_didik_id as flag_pendaftar'
			);

	    if($request->searchText){
	    	// $count = $count->where('peserta_didik.nisn', 'ilike', '%'.$searchText.'%')->orWhere('peserta_didik.nama', 'ilike', '%'.$searchText.'%')->orWhere('peserta_didik.nik', 'ilike', '%'.$searchText.'%');
			// $pds = $pds->where('peserta_didik.nisn', 'ilike', '%'.$searchText.'%')->orWhere('peserta_didik.nama', 'ilike', '%'.$searchText.'%')->orWhere('peserta_didik.nik', 'ilike', '%'.$searchText.'%');
			
			$count = $count->where(function ($query) use ($searchText){
                $query->where('peserta_didik.nama', 'ilike', '%'.$searchText.'%')
					->orWhere('peserta_didik.nisn', 'ilike', '%'.$searchText.'%')
					->orWhere('peserta_didik.nik', 'ilike', '%'.$searchText.'%');
            });
			$pds = $pds->where(function ($query) use ($searchText){
                $query->where('peserta_didik.nama', 'ilike', '%'.$searchText.'%')
					->orWhere('peserta_didik.nisn', 'ilike', '%'.$searchText.'%')
					->orWhere('peserta_didik.nik', 'ilike', '%'.$searchText.'%');
            });
		}
		
		if($tingkat_akhir_saja == 1){
			$count = $count->whereIn('peserta_didik.tingkat_pendidikan_id', array(6,9));
	    	$pds = $pds->whereIn('peserta_didik.tingkat_pendidikan_id', array(6,9));
		}

		// return $pds->toSql();die;

	    $count = $count->count();
	    $pds = $pds->get();

	    return response(
	        [
	            'rows' => $pds,
	            'count' => count($pds),
	            'countAll' => $count
	        ],
	        200
	    );
    }
}
