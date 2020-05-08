<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\BerkasCalon;
use DB;

class BerkasCalonController extends Controller
{
    public function index(Request $request)
	{
		$limit = $request->limit ? $request->limit : 10;
	    $offset = $request->page ? ($request->page+1) * $limit : 0;

	    $count_all = BerkasCalon::where('soft_delete', 0);
	    $sekolahs = BerkasCalon::where('soft_delete', 0)
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

	public function store(Request $request)
	{
		$berkas_calon_id = $request->berkas_calon_id;
		$data = $request->post();

		$cek = BerkasCalon::where('soft_delete', 0)
			->where('berkas_calon_id', $request->berkas_calon_id)
			->where('jenis_berka_id', $request->jenis_berka_id);

		if($cek->count() === 0){
			$cek = 0;
		}elseif(!$request->berkas_calon_id){
			$cek = 0;
		}else{
			$cek = $cek->first();

			$berkas_calon_id = $berkas_calon_id ? $berkas_calon_id : $cek->berkas_calon_id;
			$cek = 1;
		}

		if($cek == 0){
			$berkas_calon = BerkasCalon::create($data);
		}else{
			$berkas_calon = BerkasCalon::where('berkas_calon_id', $berkas_calon_id)->update($data);
		}

		return response([ 'rows' => $berkas_calon ], 201);
	}

	public function destroy($id)
    {
        $berkas_calon = BerkasCalon::where('berkas_calon_id', $id)->update(['soft_delete' => 1]);

        return response([ 'rows' => $berkas_calon ], 201);
    }

	public function uploadFile(Request $request)
	{
		$data = $request->all();
		$jenis_berka = $request->jenis_berka;
        $file = $data['image'];

        $ext = $file->getClientOriginalExtension();
        $name = $file->getClientOriginalName();


        $destinationPath = base_path('/public/assets/berkas_calon/'.$jenis_berka);
        $upload = $file->move($destinationPath, $name);

        $msg = $upload ? 'Success Upload File' : 'Error Upload File';
        return response(['msg' => $msg]);
	}
}
