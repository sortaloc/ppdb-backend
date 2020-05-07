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

        $count = DB::connection('sqlsrv_2')->table('ppdb.sekolah')->where('soft_delete', 0);
        $sekolahs = DB::connection('sqlsrv_2')
        	->table('ppdb.sekolah')
        	->where('soft_delete', 0)
        	->limit($limit)
        	->offset($offset)
        	->orderBy('create_date', 'DESC');

        if($searchText){
        	$count = $count->where('npsn', 'like', '%'.$searchText.'%')->orWhere('nama', 'like', '%'.$searchText.'%');
        	$sekolahs = $sekolahs->where('npsn', 'like', '%'.$searchText.'%')->orWhere('nama', 'like', '%'.$searchText.'%');
        }

        $count = $count->count();
        $sekolahs = $sekolahs->get();

        return response(
            [
                'data' => $sekolahs,
                'count' => count($sekolahs),
                'countAll' => $count
            ],
            200
        );
    }
}
