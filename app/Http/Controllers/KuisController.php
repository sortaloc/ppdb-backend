<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Tymon\JWTAuth\Facades\JWTFactory;

use App\Http\Controllers\RuangController;

class KuisController extends Controller
{
    static public function getSkema(){
        $return = array();
        $return['dbo'] = 'mb';
        $return['ref'] = 'mb_ref';

        return $return;
    }
    
    static public function generateUUID()
    {
        // return self::getSkema()['dbo'];

        $uuid = DB::connection('sqlsrv_2')
        ->table(DB::raw('pengguna'))
        ->select(DB::raw('uuid_generate_v4() as uuid'))
        ->first();

        return $uuid->{'uuid'};
    }

    public function simpanJawabanKuis(Request $request){
        $kuis_id = $request->input('kuis_id');
        $pengguna_id = $request->input('pengguna_id');
        $pertanyaan_kuis_id = $request->input('pertanyaan_kuis_id');
        $pilihan_pertanyaan_kuis_id = $request->input('pilihan_pertanyaan_kuis_id');
        $nilai = $request->input('nilai');
        $isian = $request->input('isian');
        $jawaban_kuis_id = self::generateUUID();

        $cek = DB::connection('sqlsrv_2')
                ->table('jawaban_kuis')
                ->where('pengguna_id','=',$pengguna_id)
                ->where('pertanyaan_kuis_id','=',$pertanyaan_kuis_id)
                ->where('soft_delete','=',0)
                ->get();

        $cek_benar = DB::connection('sqlsrv_2')
                ->table('pilihan_pertanyaan_kuis')
                ->where('pilihan_pertanyaan_kuis_id','=', $pilihan_pertanyaan_kuis_id)
                ->where('soft_delete','=',0)
                ->get();

        if(sizeof($cek_benar) > 0){

            if($cek_benar[0]->jawaban_benar == 1){
                $benar = 1;
            }else{
                $benar = 0;
            }

        }else{
            //harusnya sih ada. error ini
            $benar = 0;
        }

        if(sizeof($cek) > 0){
            //update
            $exe = DB::connection('sqlsrv_2')->table('jawaban_kuis')
            ->where('pengguna_id','=',$pengguna_id)
            ->where('pertanyaan_kuis_id','=',$pertanyaan_kuis_id)
            ->update([
                'pilihan_pertanyaan_kuis_id' => $pilihan_pertanyaan_kuis_id,
                'nilai' => $nilai,
                'isian' => $isian,
                'benar' => $benar,
                'last_update' => date('Y-m-d H:i:s')
            ]);

            $label = 'INSERT';
        }else{
            //insert
            $exe = DB::connection('sqlsrv_2')->table('jawaban_kuis')->insert([
                'jawaban_kuis_id' => $jawaban_kuis_id,
                'pengguna_id' =>  $pengguna_id,
                'kuis_id' => $kuis_id,
                'pertanyaan_kuis_id' => $pertanyaan_kuis_id,
                'pilihan_pertanyaan_kuis_id' => $pilihan_pertanyaan_kuis_id,
                'nilai' => $nilai,
                'isian' => $isian,
                'benar' => $benar
            ]);
            $label = 'INSERT';  
        }

        $return = array();
        $return['rows'] = DB::connection('sqlsrv_2')
        ->table('jawaban_kuis')
        ->where('pengguna_id','=',$pengguna_id)
        ->where('pertanyaan_kuis_id','=',$pertanyaan_kuis_id)
        ->where('soft_delete','=',0)
        ->get();
        $return['total'] =  sizeof($return['rows']);

        return $return;

    }

    public function getPertanyaanKuis(Request $request){
        $pertanyaan_kuis_id = $request->input('pertanyaan_kuis_id');
        $kuis_id = $request->input('kuis_id');

        $fetch = DB::connection('sqlsrv_2')->table('pertanyaan_kuis')
        ->where('pertanyaan_kuis.soft_delete','=',0)
        ;

        if($pertanyaan_kuis_id){
            $fetch->where('pertanyaan_kuis.pertanyaan_kuis_id','=',$pertanyaan_kuis_id);
        }
        
        if($kuis_id){
            $fetch->where('pertanyaan_kuis.kuis_id','=',$kuis_id);
        }

        $fetch =  $fetch->orderBy('create_date','ASC')->get();

        for ($iData=0; $iData < sizeof($fetch); $iData++) { 
            $fetch[$iData]->{'pilihan_pertanyaan_kuis'} = (object)array();
            
            $fetch_pertanyaan = DB::connection('sqlsrv_2')->table('pilihan_pertanyaan_kuis')
            ->where('pertanyaan_kuis_id','=',$fetch[$iData]->{'pertanyaan_kuis_id'})->get();
            
            for ($iPertanyaan=0; $iPertanyaan < sizeof($fetch_pertanyaan); $iPertanyaan++) { 
                
                $fetch[$iData]->{'pilihan_pertanyaan_kuis'}->{$fetch_pertanyaan[$iPertanyaan]->{'pilihan_pertanyaan_kuis_id'}} = $fetch_pertanyaan[$iPertanyaan];
                
            }

        }

        $return = array();
        $return['total'] = sizeof($fetch);
        $return['rows'] = $fetch;

        return $return;
    }

    // public function getPeringkatPenggunaKuis(Request $request){

    // }

    public function getKuisDiikuti(Request $request){
        $pengguna_id = $request->input('pengguna_id');

        $fetch_cek = DB::connection('sqlsrv_2')->table('pengguna_kuis')
        ->join('pengguna','pengguna.pengguna_id','=','pengguna_kuis.pengguna_id')
        ->join('kuis','kuis.kuis_id','=','pengguna_kuis.kuis_id')
        ->join(DB::raw("(select kuis_id, sum(1) as total from pengguna_kuis where soft_delete = 0 group by kuis_id) as jumtot"), 'jumtot.kuis_id','=', 'pengguna_kuis.kuis_id')
        // ->join(, 'peringkat.pengguna_id','=', 'pengguna_kuis.pengguna_id')
        ->join(DB::raw("(SELECT ROW_NUMBER
            () OVER ( PARTITION BY kuis_id ORDER BY status_mengerjakan_id DESC, COALESCE(skor,0) DESC ) AS peringkat ,
            *
        FROM
            pengguna_kuis 
        WHERE
            soft_delete = 0) as peringkat"), function($join)
        {
            $join->on('peringkat.pengguna_id','=', 'pengguna_kuis.pengguna_id');
            $join->on('peringkat.kuis_id','=', 'pengguna_kuis.kuis_id');
        })
        ->where('pengguna_kuis.pengguna_id','=',DB::raw("'".$pengguna_id."'"))
        // ->where('pengguna_kuis.kuis_id','=',$kuis_id)
        ->where('pengguna_kuis.soft_delete','=',DB::raw('0'))
        ->select(
            'pengguna_kuis.*',
            'kuis.*',
            'pengguna.nama as nama_pengguna',
            'peringkat.peringkat',
            'jumtot.total as total_peserta',
            'pengguna_kuis.create_date as tanggal_mengerjakan'
        )
        // ->toSql();
        ->get();

        // return $fetch_cek;die;

        $return = array();
        $return['rows'] = $fetch_cek;
        $return['total'] = sizeof($fetch_cek);

        return $return;
    }

    public function getPenggunaKuis(Request $request){
        $pengguna_id = $request->input('pengguna_id');
        $order_by_peringkat = $request->input('order_by_peringkat');
        $kuis_id = $request->input('kuis_id');
        // $kode_kuis = $request->input('kode_kuis');

        $fetch_cek = DB::connection('sqlsrv_2')->table('pengguna_kuis')
        ->join('pengguna','pengguna.pengguna_id','=','pengguna_kuis.pengguna_id')
        ->join(DB::raw("(select kuis_id, sum(1) as total from pengguna_kuis where kuis_id = '".$kuis_id."' group by kuis_id) as jumtot"), 'jumtot.kuis_id','=', 'pengguna_kuis.kuis_id')
        ->join(DB::raw("(SELECT ROW_NUMBER
            () OVER ( ORDER BY skor DESC ) AS peringkat ,
            *
        FROM
            pengguna_kuis 
        WHERE
            soft_delete = 0 
            AND kuis_id = '".$kuis_id."') as peringkat"), 'peringkat.pengguna_id','=', 'pengguna_kuis.pengguna_id')
        ->where('pengguna_kuis.kuis_id','=',DB::raw("'".$kuis_id."'"))
        ->where('pengguna_kuis.soft_delete','=',DB::raw('0'))
        ->select(
            'pengguna_kuis.*',
            'pengguna.nama as nama_pengguna',
            'peringkat.peringkat',
            'jumtot.total as total_peserta'
        );

        if($pengguna_id){
            $fetch_cek->where('pengguna_kuis.pengguna_id','=',DB::raw("'".$pengguna_id."'"));
        }

        if($order_by_peringkat == 'Y'){
            $fetch_cek->orderBy('peringkat.peringkat','ASC');
        }

        $fetch_cek = $fetch_cek->get();

        // return $fetch_cek;die;

        $return = array();
        $return['rows'] = $fetch_cek;
        $return['total'] = sizeof($fetch_cek);

        return $return;
    }
    
    public function getKuisRuang(Request $request){
        $ruang_id = $request->input('ruang_id');
        // $kode_kuis = $request->input('kode_kuis');

        $fetch_cek = DB::connection('sqlsrv_2')->table('kuis_ruang')
        ->join('kuis','kuis.kuis_id','=','kuis_ruang.kuis_id')
        ->join('pengguna','pengguna.pengguna_id','=','kuis.pengguna_id')
        ->where('kuis_ruang.ruang_id','=',DB::raw("'".$ruang_id."'"))
        ->where('kuis_ruang.soft_delete','=',DB::raw('0'))
        ->select(
            'kuis.*',
            'pengguna.nama as pengguna'
        )
        ;

        $fetch_cek = $fetch_cek->get();

        // return $fetch_cek;die;

        $return = array();
        $return['rows'] = $fetch_cek;
        $return['total'] = sizeof($fetch_cek);

        return $return;
    }

    public function simpanPenggunaKuis(Request $request){
        $pengguna_id = $request->input('pengguna_id');
        $kuis_id = $request->input('kuis_id');
        $status_mengerjakan_id = $request->input('status_mengerjakan_id');
        $skor = $request->input('skor');
        $total = $request->input('total');
        $benar = $request->input('benar');
        $salah = $request->input('salah');
        $pertanyaan_kuis_id_terakhir = $request->input('pertanyaan_kuis_id_terakhir');

        $fetch_cek = DB::connection('sqlsrv_2')->table('pengguna_kuis')
        ->where('pengguna_id','=',$pengguna_id)
        ->where('kuis_id','=',$kuis_id)
        ->where('soft_delete','=',DB::raw('0'))
        ->get();

        if(sizeof($fetch_cek) > 0){
            //update
            $exe = DB::connection('sqlsrv_2')
            ->table('pengguna_kuis')
            ->where('pengguna_id','=',$pengguna_id)
            ->where('kuis_id','=',$kuis_id)
            ->update([
                'status_mengerjakan_id' => ($status_mengerjakan_id ? $status_mengerjakan_id : 1),
                'pertanyaan_kuis_id_terakhir' => $pertanyaan_kuis_id_terakhir,
                'skor' => $skor,
                'total' => $total,
                'benar' => $benar,
                'salah' => $salah,
                'last_update' => date('Y-m-d H:i:s')
            ]);

            $label = 'UPDATE';
        }else{
            //insert
            $exe = DB::connection('sqlsrv_2')
            ->table('pengguna_kuis')
            ->insert([
                'pengguna_id' => $pengguna_id,
                'kuis_id' => $kuis_id,
                'status_mengerjakan_id' => ($status_mengerjakan_id ? $status_mengerjakan_id : 1),
                'pertanyaan_kuis_id_terakhir' => $pertanyaan_kuis_id_terakhir
            ]);
            $label = 'INSERT';
        }

        $return = array();

        if($exe){
            $return['rows'] = DB::connection('sqlsrv_2')->table('pengguna_kuis')
                                ->where('pengguna_id','=',$pengguna_id)
                                ->where('kuis_id','=',$kuis_id)
                                ->get();
            $return['status'] = 'BERHASIL';
            $return['label'] = $label;
        }else{
            $return['status'] = 'GAGAL';
            $return['label'] = $label;
        }

        return $return;
    }

    public function getKuis(Request $request){
        $kuis_id = $request->input('kuis_id');
        $kode_kuis = $request->input('kode_kuis');
        $pengguna_id = $request->input('pengguna_id');
        $tampil_jumlah_peserta = $request->input('tampil_jumlah_peserta');

        $fetch = DB::connection('sqlsrv_2')->table('kuis')
        ->join('pengguna','pengguna.pengguna_id','=','kuis.pengguna_id')
        ->leftJoin('ref.jenjang as jenjang','jenjang.jenjang_id','=','kuis.jenjang_id')
        ->leftJoin('ref.tingkat_pendidikan as tingkat','tingkat.tingkat_pendidikan_id','=','kuis.tingkat_pendidikan_id')
        ->leftJoin('ref.mata_pelajaran as mapel','mapel.mata_pelajaran_id','=','kuis.mata_pelajaran_id')
        ->leftJoin(DB::raw("(select kuis_id, sum(1) as jumlah_pertanyaan from pertanyaan_kuis where soft_delete = 0 group by kuis_id) as jumlah_pertanyaan"),'jumlah_pertanyaan.kuis_id','=','kuis.kuis_id')
        ->where('kuis.soft_delete','=',0)
        ->select(
            'kuis.*',
            'jenjang.nama as jenjang',
            'tingkat.nama as tingkat_pendidikan',
            'mapel.nama as mata_pelajaran',
            'pengguna.nama as pengguna',
            'jumlah_pertanyaan.jumlah_pertanyaan'
        )
        ;

        if($kuis_id){
            $fetch->where('kuis.kuis_id','=',$kuis_id);
        }
        
        if($kode_kuis){
            $fetch->where('kuis.kode_kuis','=',$kode_kuis);
        }
        
        if($pengguna_id){
            $fetch->where('kuis.pengguna_id','=',$pengguna_id);
        }

        if($tampil_jumlah_peserta ==  'Y'){
            $fetch->leftJoin(DB::raw("(select kuis_id, sum(1) as total from pengguna_kuis where soft_delete = 0 group by kuis_id) as jumlah_peserta"),'jumlah_peserta.kuis_id','=','kuis.kuis_id');
            $fetch->select(
                'kuis.*',
                'jenjang.nama as jenjang',
                'tingkat.nama as tingkat_pendidikan',
                'mapel.nama as mata_pelajaran',
                'pengguna.nama as pengguna',
                'jumlah_pertanyaan.jumlah_pertanyaan',
                'jumlah_peserta.total as jumlah_peserta'
            );
        }

        $fetch =  $fetch->orderBy('create_date','DESC')->get();

        for ($iData=0; $iData < sizeof($fetch); $iData++) { 
            $fetch[$iData]->{'pertanyaan_kuis'} = (object)array();
            
            $fetch_pertanyaan = DB::connection('sqlsrv_2')->table('pertanyaan_kuis')
            ->where('kuis_id','=',$fetch[$iData]->{'kuis_id'})->orderBy('create_date','ASC')->get();
            
            for ($iPertanyaan=0; $iPertanyaan < sizeof($fetch_pertanyaan); $iPertanyaan++) { 
                
                $fetch[$iData]->{'pertanyaan_kuis'}->{$fetch_pertanyaan[$iPertanyaan]->{'pertanyaan_kuis_id'}} = $fetch_pertanyaan[$iPertanyaan];
                
                $fetch[$iData]->{'pertanyaan_kuis'}->{$fetch_pertanyaan[$iPertanyaan]->{'pertanyaan_kuis_id'}}->{'pilihan_pertanyaan_kuis'} = (object)array();
            
                $fetch_pilihan = DB::connection('sqlsrv_2')->table('pilihan_pertanyaan_kuis')
                ->where('pertanyaan_kuis_id','=',$fetch_pertanyaan[$iPertanyaan]->{'pertanyaan_kuis_id'})->orderBy('create_date','ASC')->get();

                for ($iPilihan=0; $iPilihan < sizeof($fetch_pilihan); $iPilihan++) { 
                    $fetch[$iData]->{'pertanyaan_kuis'}->{$fetch_pertanyaan[$iPertanyaan]->{'pertanyaan_kuis_id'}}->{'pilihan_pertanyaan_kuis'}->{$fetch_pilihan[$iPilihan]->{'pilihan_pertanyaan_kuis_id'}} = $fetch_pilihan[$iPilihan];
                    // $fetch[$iData]->{'pertanyaan_kuis'}->{$fetch_pertanyaan[$iPertanyaan]->{'pertanyaan_kuis_id'}} = $fetch_pilihan[$iPilihan];
                }
            }

            

        }

        $return = array();
        $return['total'] = sizeof($fetch);
        $return['rows'] = $fetch;

        return $return;
    }
    
    public function simpanKuis(Request $request){
        // return "oke";
        $data = $request->all();
        $gambar_kuis = $request->input('gambar_kuis') ? $request->input('gambar_kuis') : rand(1,8).".jpg";
        $ruang_id = $request->input('ruang_id') ? $request->input('ruang_id') : null;
        // return $data;die;

        if($request->input('jenjang_id')){
            $jenjang_id = $request->input('jenjang_id');
        }else{
            $jenjang_id = null;
        }

        if($request->input('tingkat_pendidikan_id')){
            $tingkat_pendidikan_id = $request->input('tingkat_pendidikan_id');
        }else{
            $tingkat_pendidikan_id = null;
        }

        if($request->input('mata_pelajaran_id')){
            $mata_pelajaran_id = $request->input('mata_pelajaran_id');
        }else{
            $mata_pelajaran_id = null;
        }

        //simpan kuisnya dulu
        $query_cek_kuis = DB::connection('sqlsrv_2')
                            ->table('kuis')
                            ->where('kuis_id','=', $data['kuis_id'])
                            ->get();

        // return sizeof($query_cek_kuis);

        if(sizeof($query_cek_kuis) > 0){
            //update
            $exe = DB::connection('sqlsrv_2')->table('kuis')
            ->where('kuis_id','=', $data['kuis_id'])
            ->update([
                'judul' => $data['judul'],
                'keterangan' => $data['keterangan'] ? $data['keterangan'] : '',
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'publikasi' => $data['publikasi'],
                'jenjang_id'=> $jenjang_id,
                'tingkat_pendidikan_id'=> $tingkat_pendidikan_id,
                'mata_pelajaran_id'=> $mata_pelajaran_id,
                'gambar_kuis' => $gambar_kuis,
                'last_update' => date('Y-m-d H:i:s')
            ]);


        }else{
            //insert
            $exe = DB::connection('sqlsrv_2')->table('kuis')
            ->insert([
                'kuis_id' => $data['kuis_id'],
                'pengguna_id' => $data['pengguna_id'],
                'judul' => $data['judul'],
                'keterangan' => $data['keterangan'] ? $data['keterangan'] : '',
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'jenjang_id'=> $jenjang_id,
                'tingkat_pendidikan_id'=> $tingkat_pendidikan_id,
                'mata_pelajaran_id'=> $mata_pelajaran_id,
                'publikasi' => $data['publikasi'],
                'kode_kuis' => RuangController::generateRandomString(10),
                'gambar_kuis' => $gambar_kuis,
                'create_date' => date('Y-m-d H:i:s'),
                'last_update' => date('Y-m-d H:i:s')
            ]);

        }

        if($exe){
            //insert/update berhasil

            //simpan pertanyaan kuisnya
            // return "berhasil simpan kuis";
            foreach ($data['pertanyaan_kuis'] as $key => $value) {
                $query_cek_pertanyaan_kuis = DB::connection('sqlsrv_2')
                                                ->table('pertanyaan_kuis')
                                                ->where('pertanyaan_kuis_id','=', $value['pertanyaan_kuis_id'])
                                                ->get();
                
                if(sizeof($query_cek_pertanyaan_kuis) > 0){
                    //update
                    $exePertanyaanKuis = DB::connection('sqlsrv_2')->table('pertanyaan_kuis')
                    ->where('pertanyaan_kuis_id','=',$value['pertanyaan_kuis_id'])
                    ->update([
                        'teks' => $value['teks'],
                        'pengguna_id' => $data['pengguna_id'],
                        'tanggal' => date('Y-m-d H:i:s'),
                        'kuis_id' => $data['kuis_id'],
                        'last_update' => date('Y-m-d H:i:s')
                    ]);

                }else{
                    //insert
                    $exePertanyaanKuis = DB::connection('sqlsrv_2')->table('pertanyaan_kuis')
                    ->insert([
                        'pertanyaan_kuis_id' => $value['pertanyaan_kuis_id'],
                        'teks' => $value['teks'],
                        'pengguna_id' => $data['pengguna_id'],
                        'tanggal' => date('Y-m-d H:i:s'),
                        'kuis_id' => $data['kuis_id'],
                        'create_date' => date('Y-m-d H:i:s'),
                        'last_update' => date('Y-m-d H:i:s')
                    ]);

                }

                if($exePertanyaanKuis){
                    
                    foreach ($value['pilihan_pertanyaan_kuis'] as $keyPilihan => $valuePilihan) {
                        $query_cek_pilihan = DB::connection('sqlsrv_2')
                                            ->table('pilihan_pertanyaan_kuis')
                                            ->where('pilihan_pertanyaan_kuis_id','=', $valuePilihan['pilihan_pertanyaan_kuis_id'])
                                            ->get();
                        
                        if(sizeof($query_cek_pilihan) > 0){
                            //update
                            $exePilihan = DB::connection('sqlsrv_2')->table('pilihan_pertanyaan_kuis')
                            ->where('pilihan_pertanyaan_kuis_id','=', $valuePilihan['pilihan_pertanyaan_kuis_id'])
                            ->update([
                                'teks' => $valuePilihan['teks'],
                                'pengguna_id' => $data['pengguna_id'],
                                'pertanyaan_kuis_id' => $value['pertanyaan_kuis_id'],
                                'jawaban_benar' => $valuePilihan['jawaban_benar'],
                                'last_update' =>date('Y-m-d H:i:s')
                            ]);

                        }else{
                            //insert
                            $exePilihan = DB::connection('sqlsrv_2')->table('pilihan_pertanyaan_kuis')
                            ->insert([
                                'pilihan_pertanyaan_kuis_id' => $valuePilihan['pilihan_pertanyaan_kuis_id'],
                                'teks' => $valuePilihan['teks'],
                                'pengguna_id' => $data['pengguna_id'],
                                'pertanyaan_kuis_id' => $value['pertanyaan_kuis_id'],
                                'jawaban_benar' => $valuePilihan['jawaban_benar'],
                                'create_date' => date('Y-m-d H:i:s'),
                                'last_update' => date('Y-m-d H:i:s')
                            ]);

                        }
                    }

                }else{
                    //gagal tambah pertanyaan kuis
                }

            }

            //tambah ke kuis_ruang
            if($ruang_id){

                $query_cek_kuis_ruang = DB::connection('sqlsrv_2')
                                ->table('kuis_ruang')
                                ->where('kuis_id','=', $data['kuis_id'])
                                ->where('ruang_id','=', $ruang_id)
                                ->get();
                
                if(sizeof($query_cek_kuis_ruang) > 0){
                    //update
                }else{
                    //insert
                    $exe_kuis_ruang = DB::connection('sqlsrv_2')
                                    ->table('kuis_ruang')
                                    ->insert([
                                        'kuis_id' => $data['kuis_id'],
                                        'ruang_id' => $ruang_id,
                                        'create_date' => date('Y-m-d H:i:s')
                                    ]);
                }
            }

        }else{
            return "gagal simpan kuis";
        }

        // return $data['pertanyaan_kuis']['c05c2341-389f-4a3f-b7ec-7a59cb0713f4'];
    }
}