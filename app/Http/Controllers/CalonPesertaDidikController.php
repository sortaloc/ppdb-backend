<?php

namespace App\Http\Controllers;

use App\CalonPesertaDidik AS CalonPD;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
	            'data' => $calonPDs,
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
		$data 	= $request->post();

		if($pd_id){
			$cek_pd = CalonPD::where('calon_peserta_didik_id', $pd_id)
				->count();
		}else{
			$cek = 0;
		}

		if($cek_pd != 0){
			$calon_pd = CalonPD::where('calon_peserta_didik_id', $pd_id)
				->update($data);
		}else{
			$data['calon_peserta_didik_id'] = $uuid;
			$calon_pd = CalonPD::create($data);
		}

		return response([
            'rows' => $calon_pd,
        ], 201);
	}
}
