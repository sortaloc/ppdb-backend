<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\PilihanSekolah;
use DB;

class PilihsekolahController extends Controller
{
    public function index(Request $request)
    {
    	$limit = $request->limit ? $request->limit : 10;
        $offset = $request->page ? ($request->page * $limit) : 0;
        $calon_pd = $request->calon_peserta_didik_id ? $request->calon_peserta_didik_id : null;

        $count = PilihanSekolah::where('soft_delete', 0);
        $pilihan_sekolah = PilihanSekolah::select(
        		'ppdb.pilihan_sekolah.*',
        		'sekolah.nama AS nama_sekolah'
        	)
        	->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
        	->where('ppdb.pilihan_sekolah.soft_delete', 0)
        	->limit($limit)
        	->offset($offset)
        	->orderBy('ppdb.pilihan_sekolah.urut_pilihan', 'ASC');

        if($request->calon_peserta_didik_id){
            $count = $count->where('calon_peserta_didik_id', $calon_pd);
            $pilihan_sekolah = $pilihan_sekolah->where('calon_peserta_didik_id', $calon_pd);
        }

        $count = $count->count();
        $pilihan_sekolah = $pilihan_sekolah->get();

        return response(
            [
                'rows' => $pilihan_sekolah,
                'count' => count($pilihan_sekolah),
                'countAll' => $count
            ],
            200
        );
    }

    public function store(Request $request)
    {
        $uuid   = Str::uuid();
        $pilihan_id  = $request->pilihan_sekolah_id ? $request->pilihan_sekolah_id : null;
        $data   = $request->all();

        $data['soft_delete'] = 0;
        $msg = '';

        if($pilihan_id){
            $cek = PilihanSekolah::where('pilihan_sekolah_id', $pilihan_id)
                ->where('soft_delete', 0)
                ->count();
        }else{
            $cek = 0;
        }

        if($cek != 0){
            $pilihan_sekolah = PilihanSekolah::where('pilihan_sekolah_id', $pilihan_id)
                ->update($data);
            $pilihan_sekolah = PilihanSekolah::find($pilihan_id)->first();
        }else{
            $cek_duplikast = PilihanSekolah::where('calon_peserta_didik_id', $request->calon_peserta_didik_id)->where('sekolah_id', $request->sekolah_id)->count();

            if($cek_duplikast == 1){
                $msg = "sekolah_sudah_dipilih";
                $pilihan_sekolah = [];
            }else{
                $data['pilihan_sekolah_id'] = $pilihan_id ? $pilihan_id : $uuid;
                $data['urut_pilihan'] = (PilihanSekolah::where('calon_peserta_didik_id', $request->calon_peserta_didik_id)->count() + 1);            
                $pilihan_sekolah = PilihanSekolah::create($data);
            }
        }

        return response([ 'rows' => $pilihan_sekolah, 'msg' => $msg ], 201);
    }

    public function destroy($id)
    {
        $pilihan_sekolah = PilihanSekolah::where('pilihan_sekolah_id', $id)->update(['soft_delete' => 1]);

        return response([ 'rows' => $pilihan_sekolah ], 200);
    }
}
