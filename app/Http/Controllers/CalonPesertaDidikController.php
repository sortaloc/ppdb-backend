<?php

namespace App\Http\Controllers;

use App\CalonPesertaDidik AS CalonPD;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\PilihanSekolah;
use DB;

class CalonPesertaDidikController extends Controller
{
	public function index(Request $request)
	{
		$limit = $request->limit ? $request->limit : 10;
	    $offset = $request->page ? ($request->page * $limit) : 0;

	    $count = CalonPD::where('soft_delete', 0);
	    $calonPDs = CalonPD::where('soft_delete', 0)
	    	->limit($limit)
	    	->offset($offset)
	    	->orderBy('create_date', 'DESC');

	    $count = $count->count();
	    $calonPDs = $calonPDs->get();

	    return response(
	        [
	            'rows' => $calonPDs,
	            'count' => count($calonPDs),
	            'countAll' => $count
	        ],
	        200
	    );
	}

	public function store(Request $request)
	{
		$uuid 	= Str::uuid();
		$pd_id 	= $request->calon_peserta_didik_id ? $request->calon_peserta_didik_id : null;
		$data 	= $request->all();

		$data['soft_delete'] = 0;

		if($pd_id){
			$cek_pd = CalonPD::where('calon_peserta_didik_id', $pd_id)
				->where('soft_delete', 0)
				->count();
		}else{
			$cek_pd = 0;
		}

		if($cek_pd != 0){
			$calon_pd = CalonPD::where('calon_peserta_didik_id', $pd_id)
				->update($data);
			$calon_pd = CalonPD::find($pd_id)->first();
		}else{
			$data['calon_peserta_didik_id'] = $pd_id ? $pd_id : $uuid;
			$calon_pd = CalonPD::create($data);
		}

		return response([ 'rows' => $calon_pd ], 201);
	}

	public function destroy($id)
    {
        $calon_pd = CalonPD::where('calon_peserta_didik_id', $id)->update(['soft_delete' => 1]);

        return response([ 'rows' => $calon_pd ], 201);
    }

    public function print($id)
    {
    	$calon_pd = CalonPD::where('calon_peserta_didik_id', $id)->first();

    	$pilihan_sekolah = PilihanSekolah::where('calon_peserta_didik_id', $id)
    		->select(
    			'ppdb.pilihan_sekolah.*',
    			'sekolah.nama AS nama_sekolah',
    			'jalur.nama AS nama_jalur'
    		)
    		->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
    		->leftJoin('ref.jalur AS jalur', 'ppdb.pilihan_sekolah.jalur_id', '=', 'jalur.jalur_id')
    		->orderBy('urut_pilihan', 'ASC')
    		->get();

    	$rows['calon_pd'] = $calon_pd;
    	$rows['pilihan_sekolah'] = $pilihan_sekolah;

    	return response([ 'rows' => $rows ], 201);
    }
}
