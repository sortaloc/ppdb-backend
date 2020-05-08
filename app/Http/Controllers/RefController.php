<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jalur;
use DB;

class RefController extends Controller
{
    public function getJenjang(Request $request){
        $return = array();

        $fetch =DB::connection('sqlsrv_2')->table('ref.jenjang')->whereNull('expired_date')->get();

        $return['total'] = sizeof($fetch);
        $return['rows'] = $fetch;

        return $return;
    }
    
    public function getMataPelajaran(Request $request){
        $return = array();

        $fetch =DB::connection('sqlsrv_2')->table('ref.mata_pelajaran')->whereNull('expired_date')->get();

        $return['total'] = sizeof($fetch);
        $return['rows'] = $fetch;

        return $return;
    }
    
    public function getTingkatPendidikan(Request $request){
        $return = array();

        $fetch = DB::connection('sqlsrv_2')->table('ref.tingkat_pendidikan')->whereNull('expired_date');

        if($request->input('jenjang_id')){
            switch ((int)$request->input('jenjang_id')) {
                case 1:
                    $fetch->whereIn('tingkat_pendidikan_id', array(1,2,3,4,5,6));
                    break;
                case 2:
                    $fetch->whereIn('tingkat_pendidikan_id', array(7,8,9));
                    break;
                case 3:
                    $fetch->whereIn('tingkat_pendidikan_id', array(10,11,12));
                    break;
                case 4:
                    $fetch->whereIn('tingkat_pendidikan_id', array(10,11,12,13));
                    break;
                default:
                    $fetch->whereIn('tingkat_pendidikan_id', array(1,2,3,4,5,6));
                    break;
            }
        }
        
        $fetch = $fetch->get();

        $return['total'] = sizeof($fetch);
        $return['rows'] = $fetch;

        return $return;
    }

    public function getJalur(Request $request)
    {
        $jalur = Jalur::where('level_jalur', 1)->get();

        $i = 0;
        foreach ($jalur as $key) {
            $sub = Jalur::where('induk_jalur_id', $key->jalur_id);

            if($sub->count() != 0){
                $jalur[$i]->children = $sub->get();
            }

            $i++;
        }

        return $jalur;
    }

    public function getmst_wilayah(Request $request)
    {
        $mst_wilayah = DB::connection('sqlsrv_2')->table('ref.mst_wilayah');

        if($request->id_level_wilayah){
            $mst_wilayah = $mst_wilayah->where('id_level_wilayah', $request->id_level_wilayah);
        }

        if($request->kode_wilayah){
            $mst_wilayah = $mst_wilayah->where('kode_wilayah', $request->kode_wilayah);
        }

        if($request->mst_kode_wilayah){
            $mst_wilayah = $mst_wilayah->where('mst_kode_wilayah', $request->mst_kode_wilayah);
        }

        $mst_wilayah = $mst_wilayah->limit(10)->get();

        return Response(
            [
                'rows' => $mst_wilayah,
                'count' => count($mst_wilayah)
            ],
            200
        );
    }
}