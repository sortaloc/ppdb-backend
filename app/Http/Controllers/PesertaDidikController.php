<?php

namespace App\Http\Controllers;

use App\PesertaDidik AS PD;
use DB;
use Illuminate\Http\Request;

class PesertaDidikController extends Controller
{
    public function index(Request $request)
    {
    	$limit = $request->limit ? $request->limit : 20;
	    $start = $request->start ? $request->start : 0;
	    $searchText = $request->searchText ? $request->searchText : '';
	    $tingkat_akhir_saja = $request->tingkat_akhir_saja ? $request->tingkat_akhir_saja : 1;
	    $kode_wilayah = $request->kode_wilayah ? $request->kode_wilayah : null;
	    $id_level_wilayah = $request->id_level_wilayah ? $request->id_level_wilayah : 0;

	    $count = PD::join('ref.mst_wilayah as kec','kec.kode_wilayah','=',DB::raw("LEFT(peserta_didik.kode_kec_pd,6)"))
			->join('ref.mst_wilayah as kab','kec.mst_kode_wilayah','=','kab.kode_wilayah')
			->join('ref.mst_wilayah as prop','kab.mst_kode_wilayah','=','prop.kode_wilayah')
			->leftJoin('ppdb.calon_peserta_didik as calon_peserta_didik','calon_peserta_didik.calon_peserta_didik_id','=','peserta_didik.peserta_didik_id');
		$pds = PD::limit($limit)
			->join('ref.mst_wilayah as kec','kec.kode_wilayah','=',DB::raw("LEFT(peserta_didik.kode_kec_pd,6)"))
			->join('ref.mst_wilayah as kab','kec.mst_kode_wilayah','=','kab.kode_wilayah')
			->join('ref.mst_wilayah as prop','kab.mst_kode_wilayah','=','prop.kode_wilayah')
			// ->leftJoin('ppdb.calon_peserta_didik as calon_peserta_didik','calon_peserta_didik.nik','=','peserta_didik.nik')
			->leftJoin('ppdb.calon_peserta_didik as calon_peserta_didik', function ($join) {
				$join->on('calon_peserta_didik.nik', '=', 'peserta_didik.nik')
				// ->on('calon_peserta_didik.nisn','=','peserta_didik.nisn')
				->on('calon_peserta_didik.soft_delete','=',DB::raw('0'));
			})
			->leftJoin('ppdb.calon_peserta_didik as calon_peserta_didik_nisn', function ($join) {
				$join->on('calon_peserta_didik_nisn.nisn', '=', 'peserta_didik.nisn')
				// ->on('calon_peserta_didik.nisn','=','peserta_didik.nisn')
				->on('calon_peserta_didik_nisn.soft_delete','=',DB::raw('0'));
			})
			->skip($start)
			->take($limit)
			->orderBy('peserta_didik.nama', 'ASC')
			->select(
				'peserta_didik.*',
				'kec.nama as kecamatan',
				'kab.nama as kabupaten',
				'prop.nama as provinsi',
				DB::raw("(case when calon_peserta_didik.calon_peserta_didik_id is not null then calon_peserta_didik.calon_peserta_didik_id else (case when calon_peserta_didik_nisn.calon_peserta_didik_id is not null then calon_peserta_didik_nisn.calon_peserta_didik_id else null end) end) as flag_pendaftar")
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
			$count = $count->whereIn('peserta_didik.tingkat_pendidikan_id', ((int)$id_level_wilayah == 2 ? array(71,72,73,6) : array(9)) );
	    	$pds = $pds->whereIn('peserta_didik.tingkat_pendidikan_id', ((int)$id_level_wilayah == 2 ? array(71,72,73,6) : array(9))) ;
		}

		if($kode_wilayah){
			switch ($id_level_wilayah) {
				case 1:
					$count = $count->where('prop.kode_wilayah', "=", $kode_wilayah);
	    			$pds = $pds->where('prop.kode_wilayah', "=", $kode_wilayah);
					break;
				case 2:
					$count = $count->where('kab.kode_wilayah', "=", $kode_wilayah);
					$pds = $pds->where('kab.kode_wilayah', "=", $kode_wilayah);
					break;
				default:
					# code...
					break;
			}
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
