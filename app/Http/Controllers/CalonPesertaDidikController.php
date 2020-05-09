<?php

namespace App\Http\Controllers;

use App\CalonPesertaDidik AS CalonPD;
use Novay\WordTemplate\Facade AS WordTemplate;
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
	    $calon_peserta_didik_id = $request->calon_peserta_didik_id ? $request->calon_peserta_didik_id : null;

	    $count = CalonPD::where('soft_delete', 0);
	    $calonPDs = CalonPD::where('soft_delete', 0)
	    	->limit($limit)
	    	->offset($offset)
			->orderBy('create_date', 'DESC');
			
		if($calon_peserta_didik_id){
			$count->where('calon_peserta_didik_id','=',$calon_peserta_didik_id);
			$calonPDs->where('calon_peserta_didik_id','=',$calon_peserta_didik_id);
		}

	    $count = $count->count();
		$calonPDs = $calonPDs->get();
		
		for ($i=0; $i < sizeof($calonPDs); $i++) { 
			$calonPDs[$i]->sekolah_asal = DB::connection('sqlsrv_2')->table('ppdb.sekolah')->where('sekolah_id','=',$calonPDs[$i]->asal_sekolah_id)->first();
		}

	    return response(
	        [
	            'rows' => $calonPDs,
	            'count' => count($calonPDs),
	            'countAll' => $count
	        ],
	        200
	    );
	}

	public function importDariPesertaDidikDapodik(Request $request)
	{
		$peserta_didik_id = $request->input('peserta_didik_id');

		$fetch_data = DB::connection('sqlsrv_2')->table('ppdb.peserta_didik')
		->where('peserta_didik_id','=',$peserta_didik_id)->get();

		if(sizeof($fetch_data) > 0){

			$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.calon_peserta_didik')
			->where('calon_peserta_didik_id','=', $fetch_data[0]->peserta_didik_id)
			->get();

			if(sizeof($fetch_cek) > 0){
				//update
				$arrValue = [
					'last_update' => date('Y-m-d H:i:s'),
					'soft_delete' => 0,
					'nama' => $fetch_data[0]->nama,
					'nisn' => $fetch_data[0]->nisn,
					'nik' => $fetch_data[0]->nik,
					'jenis_kelamin' => $fetch_data[0]->jenis_kelamin,
					'tempat_lahir' => $fetch_data[0]->tempat_lahir,
					'tanggal_lahir' => $fetch_data[0]->tanggal_lahir,
					'asal_sekolah_id' => $fetch_data[0]->asal_sekolah_id,
					'alamat_tempat_tinggal' => $fetch_data[0]->alamat_jalan_pd,
					'kode_wilayah_kecamatan' => $fetch_data[0]->kode_kec_pd,
					'kode_wilayah_kabupaten' => substr($fetch_data[0]->kode_kec_pd,0,4).'00',
					'kode_wilayah_provinsi' => substr($fetch_data[0]->kode_kec_pd,0,2).'0000',
					'kode_pos' => null,
					'lintang' => $fetch_data[0]->lintang,
					'bujur' => $fetch_data[0]->bujur,
					'tempat_lahir_ayah' => $fetch_data[0]->nama_ayah,
					'nama_ibu' => $fetch_data[0]->nama_ibu_kandung,
					'nama_wali' => $fetch_data[0]->nama_wali,
					'orang_tua_utama' => 'ayah'
				];

				$exe = DB::connection('sqlsrv_2')
				->table('ppdb.calon_peserta_didik')
				->where('calon_peserta_didik_id','=',$fetch_data[0]->peserta_didik_id)
				->update($arrValue);

			}else{
				//insert
				$arrValue = [
					'calon_peserta_didik_id' => $fetch_data[0]->peserta_didik_id,
					'create_date' => date('Y-m-d H:i:s'),
					'last_update' => date('Y-m-d H:i:s'),
					'soft_delete' => 0,
					'nama' => $fetch_data[0]->nama,
					'nisn' => $fetch_data[0]->nisn,
					'nik' => $fetch_data[0]->nik,
					'jenis_kelamin' => $fetch_data[0]->jenis_kelamin,
					'tempat_lahir' => $fetch_data[0]->tempat_lahir,
					'tanggal_lahir' => $fetch_data[0]->tanggal_lahir,
					'asal_sekolah_id' => $fetch_data[0]->asal_sekolah_id,
					'alamat_tempat_tinggal' => $fetch_data[0]->alamat_jalan_pd,
					'kode_wilayah_kecamatan' => $fetch_data[0]->kode_kec_pd,
					'kode_wilayah_kabupaten' => substr($fetch_data[0]->kode_kec_pd,0,4).'00',
					'kode_wilayah_provinsi' => substr($fetch_data[0]->kode_kec_pd,0,2).'0000',
					'kode_pos' => null,
					'lintang' => $fetch_data[0]->lintang,
					'bujur' => $fetch_data[0]->bujur,
					'nama_ayah' => $fetch_data[0]->nama_ayah,
					'nama_ibu' => $fetch_data[0]->nama_ibu_kandung,
					'nama_wali' => $fetch_data[0]->nama_wali,
					'orang_tua_utama' => 'ayah'
				];
	
				$exe = DB::connection('sqlsrv_2')
				->table('ppdb.calon_peserta_didik')
				->insert($arrValue);
			}


			if($exe){
				return response([ 'success' => true ], 201);
			}else{
				return response([ 'success' => false ], 201);
			}
			
		}else{
			return response([ 'success' => false ], 201);
		}
	}

	public function store(Request $request){
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;

		$fetch_cek = DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')->where('calon_peserta_didik_id','=',$calon_peserta_didik_id)->get();

		if(sizeof($fetch_cek) > 0){
			//update
			$label = 'update';

			$arrValue = [
				"last_update" => date('Y-m-d H:i:s'), 
				"nik" => $request->input('nik'), 
				"jenis_kelamin" => $request->input('jenis_kelamin'), 
				"tempat_lahir" => $request->input('tempat_lahir'), 
				"tanggal_lahir" => $request->input('tanggal_lahir'), 
				"asal_sekolah_id" => $request->input('asal_sekolah_id'), 
				"alamat_tempat_tinggal" => $request->input('alamat_tempat_tinggal'), 
				"kode_wilayah_kecamatan" => $request->input('kode_wilayah_kecamatan'), 
				"kode_pos" => $request->input('kode_pos'), 
				"lintang" => $request->input('lintang'), 
				"bujur" => $request->input('bujur'), 
				"nama_ayah" => $request->input('nama_ayah'), 
				"tempat_lahir_ayah" => $request->input('tempat_lahir_ayah'), 
				"tanggal_lahir_ayah" => $request->input('tanggal_lahir_ayah'), 
				"pendidikan_terakhir_id_ayah" => $request->input('pendidikan_terakhir_id_ayah'), 
				"pekerjaan_id_ayah" => $request->input('pekerjaan_id_ayah'), 
				"alamat_tempat_tinggal_ayah" => $request->input('alamat_tempat_tinggal_ayah'), 
				"no_telepon_ayah" => $request->input('no_telepon_ayah'), 
				"nama_ibu" => $request->input('nama_ibu'), 
				"tempat_lahir_ibu" => $request->input('tempat_lahir_ibu'), 
				"pendidikan_terakhir_id_ibu" => $request->input('pendidikan_terakhir_id_ibu'), 
				"pekerjaan_id_ibu" => $request->input('pekerjaan_id_ibu'), 
				"alamat_tempat_tinggal_ibu" => $request->input('alamat_tempat_tinggal_ibu'), 
				"no_telepon_ibu" => $request->input('no_telepon_ibu'), 
				"nama_wali" => $request->input('nama_wali'), 
				"tempat_lahir_wali" => $request->input('tempat_lahir_wali'), 
				"tanggal_lahir_wali" => $request->input('tanggal_lahir_wali'), 
				"pekerjaan_id_wali" => $request->input('pekerjaan_id_wali'), 
				"tanggal_lahir_ibu" => $request->input('tanggal_lahir_ibu'), 
				"alamat_tempat_tinggal_wali" => $request->input('alamat_tempat_tinggal_wali'), 
				"no_telepon_wali" => $request->input('no_telepon_wali'), 
				"orang_tua_utama" => $request->input('orang_tua_utama'), 
				"rt" => $request->input('rt'), 
				"rw" => $request->input('rw'), 
				"pengguna_id" => $request->input('pengguna_id'), 
				"periode_kegiatan_id" => '2020', 
				"kode_wilayah_kabupaten" => $request->input('kode_wilayah_kabupaten'), 
				"kode_wilayah_provinsi" => $request->input('kode_wilayah_provinsi'), 
				"dusun" => $request->input('dusun'), 
				"nama" => $request->input('nama'), 
				"nisn" => $request->input('nisn'), 
				"pendidikan_terakhir_id_wali" => $request->input('pendidikan_terakhir_id_wali')
			];

			$exe = DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')
			->where('calon_peserta_didik_id','=',$calon_peserta_didik_id)
			->update($arrValue);
		}else{
			//insert
			$pd_id = Str::uuid();

			$arrValue = [
				"calon_peserta_didik_id" => $pd_id,
				"create_date" => date('Y-m-d H:i:s'), 
				"last_update" => date('Y-m-d H:i:s'), 
				"soft_delete" => 0, 
				"nik" => $request->input('nik'), 
				"jenis_kelamin" => $request->input('jenis_kelamin'), 
				"tempat_lahir" => $request->input('tempat_lahir'), 
				"tanggal_lahir" => $request->input('tanggal_lahir'), 
				"asal_sekolah_id" => $request->input('asal_sekolah_id'), 
				"alamat_tempat_tinggal" => $request->input('alamat_tempat_tinggal'), 
				"kode_wilayah_kecamatan" => $request->input('kode_wilayah_kecamatan'), 
				"kode_pos" => $request->input('kode_pos'), 
				"lintang" => $request->input('lintang'), 
				"bujur" => $request->input('bujur'), 
				"nama_ayah" => $request->input('nama_ayah'), 
				"tempat_lahir_ayah" => $request->input('tempat_lahir_ayah'), 
				"tanggal_lahir_ayah" => $request->input('tanggal_lahir_ayah'), 
				"pendidikan_terakhir_id_ayah" => $request->input('pendidikan_terakhir_id_ayah'), 
				"pekerjaan_id_ayah" => $request->input('pekerjaan_id_ayah'), 
				"alamat_tempat_tinggal_ayah" => $request->input('alamat_tempat_tinggal_ayah'), 
				"no_telepon_ayah" => $request->input('no_telepon_ayah'), 
				"nama_ibu" => $request->input('nama_ibu'), 
				"tempat_lahir_ibu" => $request->input('tempat_lahir_ibu'), 
				"pendidikan_terakhir_id_ibu" => $request->input('pendidikan_terakhir_id_ibu'), 
				"pekerjaan_id_ibu" => $request->input('pekerjaan_id_ibu'), 
				"alamat_tempat_tinggal_ibu" => $request->input('alamat_tempat_tinggal_ibu'), 
				"no_telepon_ibu" => $request->input('no_telepon_ibu'), 
				"nama_wali" => $request->input('nama_wali'), 
				"tempat_lahir_wali" => $request->input('tempat_lahir_wali'), 
				"tanggal_lahir_wali" => $request->input('tanggal_lahir_wali'), 
				"pekerjaan_id_wali" => $request->input('pekerjaan_id_wali'), 
				"tanggal_lahir_ibu" => $request->input('tanggal_lahir_ibu'), 
				"alamat_tempat_tinggal_wali" => $request->input('alamat_tempat_tinggal_wali'), 
				"no_telepon_wali" => $request->input('no_telepon_wali'), 
				"orang_tua_utama" => $request->input('orang_tua_utama'), 
				"rt" => $request->input('rt'), 
				"rw" => $request->input('rw'), 
				"pengguna_id" => $request->input('pengguna_id'), 
				"periode_kegiatan_id" => '2020', 
				"kode_wilayah_kabupaten" => $request->input('kode_wilayah_kabupaten'), 
				"kode_wilayah_provinsi" => $request->input('kode_wilayah_provinsi'), 
				"dusun" => $request->input('dusun'), 
				"nama" => $request->input('nama'), 
				"nisn" => $request->input('nisn'), 
				"pendidikan_terakhir_id_wali" => $request->input('pendidikan_terakhir_id_wali')
			];

			$label = 'insert';
			$exe = DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')->insert($arrValue);
		}

		if($exe){
			return response([ 'success' => true, 'rows' => DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')->where('calon_peserta_didik_id','=', ($calon_peserta_didik_id ? $calon_peserta_didik_id : $pd_id))->get() ], 201);
		}else{
			return response([ 'success' => false ], 201);
		}

		// return $label;
	}

	// public function store(Request $request)
	// {
	// 	$uuid 	= Str::uuid();
	// 	$pd_id 	= $request->calon_peserta_didik_id ? $request->calon_peserta_didik_id : $uuid;
	// 	$data 	= $request->all();

	// 	$arrValue = [
	// 		"calon_peserta_didik_id" => $pd_id,
	// 		"create_date" => date('Y-m-d H:i:s'), 
	// 		"last_update" => date('Y-m-d H:i:s'), 
	// 		"soft_delete" => 0, 
	// 		"nik" => $data['nik'], 
	// 		"jenis_kelamin" => $data['jenis_kelamin'], 
	// 		"tempat_lahir" => $data['tempat_lahir'], 
	// 		"tanggal_lahir" => $data['tanggal_lahir'], 
	// 		"asal_sekolah_id" => $data['asal_sekolah_id'], 
	// 		"alamat_tempat_tinggal" => $data['alamat_tempat_tinggal'], 
	// 		"kode_wilayah_kecamatan" => $data['kode_wilayah_kecamatan'], 
	// 		"kode_pos" => $data['kode_pos'], 
	// 		"lintang" => $data['lintang'], 
	// 		"bujur" => $data['bujur'], 
	// 		"nama_ayah" => $data['nama_ayah'], 
	// 		"tempat_lahir_ayah" => $data['tempat_lahir_ayah'], 
	// 		"tanggal_lahir_ayah" => $data['tanggal_lahir_ayah'], 
	// 		"pendidikan_terakhir_id_ayah" => $data['pendidikan_terakhir_id_ayah'], 
	// 		"pekerjaan_id_ayah" => $data['pekerjaan_id_ayah'], 
	// 		"alamat_tempat_tinggal_ayah" => $data['alamat_tempat_tinggal_ayah'], 
	// 		"no_telepon_ayah" => $data['no_telepon_ayah'], 
	// 		"nama_ibu" => $data['nama_ibu'], 
	// 		"tempat_lahir_ibu" => $data['tempat_lahir_ibu'], 
	// 		"pendidikan_terakhir_id_ibu" => $data['pendidikan_terakhir_id_ibu'], 
	// 		"pekerjaan_id_ibu" => $data['pekerjaan_id_ibu'], 
	// 		"alamat_tempat_tinggal_ibu" => $data['alamat_tempat_tinggal_ibu'], 
	// 		"no_telepon_ibu" => $data['no_telepon_ibu'], 
	// 		"nama_wali" => $data['nama_wali'], 
	// 		"tempat_lahir_wali" => $data['tempat_lahir_wali'], 
	// 		"tanggal_lahir_wali" => $data['tanggal_lahir_wali'], 
	// 		"pekerjaan_id_wali" => $data['pekerjaan_id_wali'], 
	// 		"tanggal_lahir_ibu" => $data['tanggal_lahir_ibu'], 
	// 		"alamat_tempat_tinggal_wali" => $data['alamat_tempat_tinggal_wali'], 
	// 		"no_telepon_wali" => $data['no_telepon_wali'], 
	// 		"orang_tua_utama" => $data['orang_tua_utama'], 
	// 		"rt" => $data['rt'], 
	// 		"rw" => $data['rw'], 
	// 		"pengguna_id" => $data['pengguna_id'], 
	// 		"periode_kegiatan_id" => '2020', 
	// 		"kode_wilayah_kabupaten" => $data['kode_wilayah_kabupaten'], 
	// 		"kode_wilayah_provinsi" => $data['kode_wilayah_provinsi'], 
	// 		"dusun" => $data['dusun'], 
	// 		"nama" => $data['nama'], 
	// 		"nisn" => $data['nisn'], 
	// 		"pendidikan_terakhir_id_wali" => $data['pendidikan_terakhir_id_wali']
	// 	];

	// 	$data['soft_delete'] = 0;

	// 	if($pd_id){
	// 		$cek_pd = CalonPD::where('calon_peserta_didik_id', $pd_id)
	// 			->where('soft_delete', 0)
	// 			->count();
	// 	}else{
	// 		$cek_pd = 0;
	// 	}

	// 	if($cek_pd != 0){
	// 		$calon_pd = CalonPD::where('calon_peserta_didik_id', $pd_id)
	// 			->update($arrValue);
	// 		$calon_pd = CalonPD::find($pd_id)->first();
	// 	}else{
	// 		$data['calon_peserta_didik_id'] = $pd_id ? $pd_id : $uuid;
	// 		$calon_pd = CalonPD::create($arrValue);
	// 	}

	// 	return response([ 'rows' => $calon_pd ], 201);
	// }

	public function destroy($id)
    {
        $calon_pd = CalonPD::where('calon_peserta_didik_id', $id)->update(['soft_delete' => 1]);

        return response([ 'rows' => $calon_pd ], 201);
    }

    public function print_formulir($id)
    {
    	$calon_pd = CalonPD::where('calon_peserta_didik_id', $id)
    		->select(
    			'ppdb.calon_peserta_didik.*',
    			'sekolah.nama AS asal_sekolah',
    			'kec.nama AS kecamatan',
    			'kab.nama AS kabupaten',
    			'prop.nama AS provinsi'
    		)
    		->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.calon_peserta_didik.asal_sekolah_id', '=', 'sekolah.sekolah_id')
    		->join('ref.mst_wilayah AS  kec', 'ppdb.calon_peserta_didik.kode_wilayah_kecamatan', '=', 'kec.kode_wilayah')
    		->join('ref.mst_wilayah AS  kab', 'ppdb.calon_peserta_didik.kode_wilayah_kabupaten', '=', 'kab.kode_wilayah')
    		->join('ref.mst_wilayah AS  prop', 'ppdb.calon_peserta_didik.kode_wilayah_provinsi', '=', 'prop.kode_wilayah')
    		->first();

    	$pilihan_sekolah = PilihanSekolah::where('calon_peserta_didik_id', $id)
    		->select(
    			'ppdb.pilihan_sekolah.*',
    			'sekolah.nama AS nama_sekolah',
    			'sekolah.npsn AS npsn',
    			'jalur.nama AS nama_jalur'
    		)
    		->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
    		->leftJoin('ref.jalur AS jalur', 'ppdb.pilihan_sekolah.jalur_id', '=', 'jalur.jalur_id')
    		->orderBy('urut_pilihan', 'ASC')
    		->where('ppdb.pilihan_sekolah.soft_delete', 0)
    		->get();

    	$file = public_path('template_formulir_pendaftaran.rtf');
		
		$array = array(
			'#nama' 		=> $calon_pd->nama,
			'#nik' 			=> $calon_pd->nik,
			'#tempat_lahir' => $calon_pd->tempat_lahir,
			'#tgllhr_d' 	=> date("d", strtotime($calon_pd->tanggal_lahir)),
			'#tgllhr_m' 	=> date("m", strtotime($calon_pd->tanggal_lahir)),
			'#tgllhr_y' 	=> date("Y", strtotime($calon_pd->tanggal_lahir)),
			'#asal_sekolah' => $calon_pd->asal_sekolah,
			'#alamat_tempat_tinggal' => $calon_pd->alamat_tempat_tinggal,
			'#rt' 			=> $calon_pd->rt,
			'#rw' 			=> $calon_pd->rw,
			'#dusun' 		=> $calon_pd->dusun,
			'#kecamatan' 	=> $calon_pd->kecamatan,
			'#kabupaten' 	=> $calon_pd->kabupaten,
			'#provinsi'		=> $calon_pd->provinsi,
			'#lintang' 		=> $calon_pd->lintang,
			'#bujur' 		=> $calon_pd->bujur,
			'#npsn1' 		=> @$pilihan_sekolah[0]->npsn,
			'#sekolah1' 	=> @$pilihan_sekolah[0]->nama_sekolah,
			'#npsn2' 		=> @$pilihan_sekolah[1]->npsn,
			'#sekolah2' 	=> @$pilihan_sekolah[1]->nama_sekolah,
			'#orang_tua_utama' 			=> $calon_pd->orang_tua_utama == 'ayah' ? $calon_pd->nama_ayah : $calon_pd->orang_tua_utama == 'ibu' ? $calon_pd->nama_ibu : $calon_pd->nama_wali,
			'#orang_tua_tempat_lahir' 	=> $calon_pd->orang_tua_utama == 'ayah' ? $calon_pd->tempat_lahir_ayah : $calon_pd->orang_tua_utama == 'ibu' ? $calon_pd->tempat_lahir_ibu : $calon_pd->tempat_lahir_wali,
			'#orang_tua_tanggal_lahir' 	=> $calon_pd->orang_tua_utama == 'ayah' ? $calon_pd->tanggal_lahir_ayah : $calon_pd->orang_tua_utama == 'ibu' ? $calon_pd->tanggal_lahir_ibu : $calon_pd->tanggal_lahir_wali,
			'#orang_tua_pendidikan'	 	=> $calon_pd->orang_tua_utama == 'ayah' ? $calon_pd->pendidikan_terakhir_id_ayah : $calon_pd->orang_tua_utama == 'ibu' ? $calon_pd->pendidikan_terakhir_id_ibu : $calon_pd->pendidikan_terakhir_id_wali,
			'#orang_tua_pekerjaan' 		=> $calon_pd->orang_tua_utama == 'ayah' ? $calon_pd->pekerjaan_id_ayah : $calon_pd->orang_tua_utama == 'ibu' ? $calon_pd->pekerjaan_id_ibu : $calon_pd->pekerjaan_id_wali,
			'#orang_tua_alamat_tempat_tinggal' => $calon_pd->orang_tua_utama == 'ayah' ? $calon_pd->alamat_tempat_tinggal_ayah : $calon_pd->orang_tua_utama == 'ibu' ? $calon_pd->alamat_tempat_tinggal_ibu : $calon_pd->alamat_tempat_tinggal_wali,
			'#orang_tua_no_telepon' 	=> $calon_pd->orang_tua_utama == 'ayah' ? $calon_pd->no_telepon_ayah : $calon_pd->orang_tua_utama == 'ibu' ? $calon_pd->no_telepon_ibu : $calon_pd->no_telepon_wali,
		);

		$nama_file = 'Formulir_PPDB_2019.doc';

		return $array; die;
		
		return WordTemplate::export($file, $array, $nama_file);
    }

    public function print_bukti($id)
    {
    	$calon_pd = CalonPD::where('calon_peserta_didik_id', $id)
    		->select(
    			'calon_peserta_didik.*',
    			'sekolah.nama AS asal_sekolah'
    		)
    		->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.calon_peserta_didik.asal_sekolah_id', '=', 'sekolah.sekolah_id')
    		->first();

    	$pilihan_sekolah = PilihanSekolah::where('calon_peserta_didik_id', $id)
    		->select(
    			'ppdb.pilihan_sekolah.*',
    			'sekolah.nama AS nama_sekolah',
    			'sekolah.npsn AS npsn',
    			'jalur.nama AS nama_jalur'
    		)
    		->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
    		->leftJoin('ref.jalur AS jalur', 'ppdb.pilihan_sekolah.jalur_id', '=', 'jalur.jalur_id')
    		->orderBy('urut_pilihan', 'ASC')
    		->where('ppdb.pilihan_sekolah.soft_delete', 0)
    		->get();

    	$file = public_path('template_bukti_pendaftaran.rtf');
		
		$array = array(
			'#nama' 		=> $calon_pd->nama,
			'#tempat_lahir' => $calon_pd->tempat_lahir,
			'#tgllhr_d' 	=> date("d", strtotime($calon_pd->tanggal_lahir)),
			'#tgllhr_m' 	=> date("m", strtotime($calon_pd->tanggal_lahir)),
			'#tgllhr_y' 	=> date("Y", strtotime($calon_pd->tanggal_lahir)),
			'#lintang' 		=> $calon_pd->lintang,
			'#bujur' 		=> $calon_pd->bujur,
			'#asal_sekolah' => $calon_pd->asal_sekolah,
			'#npsn1' 		=> @$pilihan_sekolah[0]->npsn,
			'#sekolah1' 	=> @$pilihan_sekolah[0]->nama_sekolah,
			'#npsn2' 		=> @$pilihan_sekolah[1]->npsn,
			'#sekolah2' 	=> @$pilihan_sekolah[1]->nama_sekolah,
		);

		$nama_file = 'Bukti_PPDB_2019.doc';

		// return $array; die;
		
		return WordTemplate::export($file, $array, $nama_file);
    }
}
