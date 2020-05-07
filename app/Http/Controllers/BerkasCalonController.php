<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class BerkasCalonController extends Controller
{
    public function index(Request $request)
	{
		$limit = $request->limit ? $request->limit : 10;
	    $offset = $request->page ? ($request->page+1) * $limit : 0;

	    $count_all = DB::connection('sqlsrv_2')->table('ppdb.berkas_calon')->where('soft_delete', 0);
	    $sekolahs = DB::connection('sqlsrv_2')
	    	->table('ppdb.berkas_calon')
	    	->where('soft_delete', 0)
	    	->limit($limit)
	    	->offset($offset)
	    	->orderBy('create_date', 'DESC');

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
}
