<?php

namespace App\Http\Controllers;

use App\PesertaDidik AS PD;
use Illuminate\Http\Request;

class PesertaDidikController extends Controller
{
    public function index(Request $request)
    {
    	$limit = $request->limit ? $request->limit : 10;
	    $offset = $request->page ? ($request->page * $limit) : 0;
	    $searchText = $request->searchText ? $request->searchText : '';

	    $count = new PD;
	    $pds = PD::limit($limit)
	    	->offset($offset)
	    	->orderBy('nama', 'ASC');

	    if($request->searchText){
	    	$count = $count->where('nisn', 'like', '%'.$searchText.'%')->orWhere('nama', 'like', '%'.$searchText.'%')->orWhere('nik', 'like', '%'.$searchText.'%');
	    	$pds = $pds->where('nisn', 'like', '%'.$searchText.'%')->orWhere('nama', 'like', '%'.$searchText.'%')->orWhere('nik', 'like', '%'.$searchText.'%');
	    }

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
