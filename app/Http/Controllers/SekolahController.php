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
            ->table('ppdb.sekolah as sekolah')
            ->join('ref.bentuk_pendidikan as bp','bp.bentuk_pendidikan_id','=','sekolah.bentuk_pendidikan_id')
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
