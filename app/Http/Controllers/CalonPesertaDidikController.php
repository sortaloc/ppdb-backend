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
	    $calon_peserta_didik_id = $request->calon_peserta_didik_id ? ($request->calon_peserta_didik_id != 'null' ? $request->calon_peserta_didik_id : null) : null;
	    $searchText = $request->searchText ? $request->searchText : null;
	    $pengguna_id = $request->pengguna_id ? $request->pengguna_id : null;

	    $count = CalonPD::where('ppdb.calon_peserta_didik.soft_delete', 0);
		$calonPDs = CalonPD::where('ppdb.calon_peserta_didik.soft_delete', 0)
			->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.calon_peserta_didik.asal_sekolah_id', '=', 'sekolah.sekolah_id')
			->leftJoin('ref.mst_wilayah AS kec', 'kec.kode_wilayah', '=', 'ppdb.calon_peserta_didik.kode_wilayah_kecamatan')
			->leftJoin('ref.mst_wilayah AS kab', 'kab.kode_wilayah', '=', 'ppdb.calon_peserta_didik.kode_wilayah_kabupaten')
			->leftJoin('ref.mst_wilayah AS prov', 'prov.kode_wilayah', '=', 'ppdb.calon_peserta_didik.kode_wilayah_provinsi')
			->leftJoin('pengguna', 'pengguna.pengguna_id', '=', 'calon_peserta_didik.pengguna_id')
	    	->limit($limit)
			->offset($offset)
			->select(
				'ppdb.calon_peserta_didik.*',
				'sekolah.nama AS sekolah_asal',
				'kec.nama as kecamatan',
				'kab.nama as kabupaten',
				'prov.nama as provinsi',
				'pengguna.nama as nama_pengguna'
			)
			->orderBy('ppdb.calon_peserta_didik.create_date', 'DESC');
			
		if($calon_peserta_didik_id){
			$count->where('ppdb.calon_peserta_didik.calon_peserta_didik_id','=',$calon_peserta_didik_id);
			$calonPDs->where('ppdb.calon_peserta_didik.calon_peserta_didik_id','=',$calon_peserta_didik_id);
		}

		if($pengguna_id){
			$count->where('ppdb.calon_peserta_didik.pengguna_id','=',$pengguna_id);
			$calonPDs->where('ppdb.calon_peserta_didik.pengguna_id','=',$pengguna_id);
		}

		if($searchText != null){
			// $count->where('ppdb.calon_peserta_didik.nik', 'ilike', '%'.$searchText.'%');
			// $calonPDs->where('ppdb.calon_peserta_didik.nik', 'ilike', '%'.$searchText.'%');
			$count = $count->where(function ($query) use ($searchText){
                $query->where('ppdb.calon_peserta_didik.nama', 'ilike', '%'.$searchText.'%')
					->orWhere('ppdb.calon_peserta_didik.nisn', 'ilike', '%'.$searchText.'%')
					->orWhere('ppdb.calon_peserta_didik.nik', 'ilike', '%'.$searchText.'%');
            });
			$calonPDs = $calonPDs->where(function ($query) use ($searchText){
                $query->where('ppdb.calon_peserta_didik.nama', 'ilike', '%'.$searchText.'%')
					->orWhere('ppdb.calon_peserta_didik.nisn', 'ilike', '%'.$searchText.'%')
					->orWhere('ppdb.calon_peserta_didik.nik', 'ilike', '%'.$searchText.'%');
            });
		}

		// return $calonPDs->toSql();die;

	    $count = $count->count();
		$calonPDs = $calonPDs->orderBy('create_date','DESC')->get();


		$i = 0;
		foreach ($calonPDs as $key) {
			$sekolah = PilihanSekolah::where('pilihan_sekolah.calon_peserta_didik_id', $key->calon_peserta_didik_id)
			->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
			->leftJoin('ref.jalur AS jalur', 'ppdb.pilihan_sekolah.jalur_id', '=', 'jalur.jalur_id')
			->leftJoin(
				DB::raw('(
					SELECT ROW_NUMBER
					() OVER (
						PARTITION BY pilihan_sekolah.jalur_id 
					ORDER BY
						pilihan_sekolah.urut_pilihan ASC,
						COALESCE ( konf.status, 0 ) DESC,
						konf.last_update ASC,	
						pilihan_sekolah.create_date ASC
					) AS urutan,
					urut_pilihan,
					COALESCE ( konf.status, 0 ) AS konfirmasi,
					konf.last_update,
					pilihan_sekolah.create_date,
					pilihan_sekolah.jalur_id,
					calon_peserta_didik.nama,
					pilihan_sekolah.sekolah_id,
					pilihan_sekolah.calon_peserta_didik_id
				FROM
					ppdb.pilihan_sekolah
					LEFT JOIN ppdb.konfirmasi_pendaftaran konf ON konf.calon_peserta_didik_id = pilihan_sekolah.calon_peserta_didik_id
					JOIN ppdb.calon_peserta_didik ON calon_peserta_didik.calon_peserta_didik_id = pilihan_sekolah.calon_peserta_didik_id 
				WHERE
					pilihan_sekolah.soft_delete = 0 
					AND calon_peserta_didik.soft_delete = 0 
				ORDER BY
					pilihan_sekolah.urut_pilihan ASC,
					COALESCE ( konf.status, 0 ) DESC,
					konf.last_update ASC,
					pilihan_sekolah.create_date ASC
				) as urutan'), function ($join) {
				$join->on('urutan.sekolah_id', '=', 'ppdb.pilihan_sekolah.sekolah_id');
				$join->on('urutan.calon_peserta_didik_id','=','ppdb.pilihan_sekolah.calon_peserta_didik_id');
			})
			->leftJoin('ppdb.kuota_sekolah as kuota','kuota.sekolah_id','=','ppdb.pilihan_sekolah.sekolah_id')
			->select(
				'pilihan_sekolah.*',
				'sekolah.npsn AS npsn',
				'sekolah.nama AS nama_sekolah',
				'jalur.nama AS jalur',
				'urutan.urutan',
				'kuota.kuota'
			)
			->where('ppdb.pilihan_sekolah.soft_delete', 0)
			->orderBy('urut_pilihan','ASC')
			->get();

			$calonPDs[$i]->pilihan_sekolah = $sekolah;

			//pas foto
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.berkas_calon')
			->where('calon_peserta_didik_id','=',$key->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->where('jenis_berkas_id','=',8)
			->get();

			if(sizeof($fetch_foto) > 0){
				$calonPDs[$i]->pas_foto = $fetch_foto[0]->nama_file;
			}else{
				$calonPDs[$i]->pas_foto = '/assets/img/generic-avatar.png';
			}

			//konfirmasi
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.konfirmasi_pendaftaran')
			->where('calon_peserta_didik_id','=',$key->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			// ->where('jenis_berkas_id','=',8)
			->get();

			if(sizeof($fetch_foto) > 0){
				$calonPDs[$i]->status_konfirmasi = $fetch_foto[0]->status;
				$calonPDs[$i]->tanggal_konfirmasi = $fetch_foto[0]->last_update;
			}else{
				$calonPDs[$i]->status_konfirmasi = 0;
				$calonPDs[$i]->tanggal_konfirmasi = '-';
			}

			//sekolah_asal
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.sekolah')
			->where('sekolah_id','=',$key->asal_sekolah_id)
			->where('soft_delete','=',0)
			->get();

			if(sizeof($fetch_foto) > 0){
				$calonPDs[$i]->sekolah_asal = $fetch_foto[0];
				// $calonPDs[$i]->tingkat_pendidikan_id = $fetch_foto[0];
			}else{
				$calonPDs[$i]->sekolah_asal = [];
			}

			$i++;
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

	public function cekNik(Request $request){
		$nik = $request->input('nik') ? $request->input('nik') : null;
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;

		if($nik){
			$fetch = DB::connection('sqlsrv_2')
			->table('ppdb.calon_peserta_didik')
			->where('nik','=',$nik)
			->where('soft_delete','=',0);

			if($calon_peserta_didik_id){
				$fetch->whereNotIn('calon_peserta_didik_id',array($calon_peserta_didik_id));
			}
			
			// return $fetch->toSql();die;

			$fetch = $fetch->get();

			// if(sizeof($fetch) > 0){
				//ada
			return response([ 'rows' => $fetch, 'count' => sizeof($fetch) ], 201);
			// }else{
			// 	//tidak ada
			// }
		}else{
			return response([ 'rows' => [], 'count' => 1 ], 201);
		}
	}

	public function cekNISN(Request $request){
		$nisn = $request->input('nisn') ? $request->input('nisn') : null;
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;

		if($nisn){
			$fetch = DB::connection('sqlsrv_2')
			->table('ppdb.calon_peserta_didik')
			->where('nisn','=',$nisn)
			->where('soft_delete','=',0);

			if($calon_peserta_didik_id){
				$fetch->whereNotIn('calon_peserta_didik_id',array($calon_peserta_didik_id));
			}
			
			// return $fetch->toSql();die;

			$fetch = $fetch->get();

			// if(sizeof($fetch) > 0){
				//ada
			return response([ 'rows' => $fetch, 'count' => sizeof($fetch) ], 201);
			// }else{
			// 	//tidak ada
			// }
		}else{
			return response([ 'rows' => [], 'count' => 1 ], 201);
		}
	}

	public function upload(Request $request)
    {
        $data = $request->all();
        $file = $data['image'];
        // $pengguna_id = $data['pengguna_id'];
        $jenis = $data['jenis'];

        if(($file == 'undefined') OR ($file == '')){
            return response()->json(['msg' => 'tidak_ada_file']);
        }

        $ext = $file->getClientOriginalExtension();
        $name = $file->getClientOriginalName();

        $destinationPath = base_path('/public/assets/berkas');
        $upload = $file->move($destinationPath, $name);

        $msg = $upload ? 'sukses' : 'gagal';

        if($upload){

			switch ($jenis) {
				case 'file_gambar_pas_foto':
					$jenis_berkas_id = 8;
					break;
				case 'file_gambar_kk':
					$jenis_berkas_id = 1;
					break;
				case 'file_gambar_kip':
					$jenis_berkas_id = 7;
					break;
				case 'file_gambar_surat_tidak_mampu':
					$jenis_berkas_id = 9;
					break;
				case 'file_gambar_surat_pindah':
					$jenis_berkas_id = 10;
					break;
				case 'file_gambar_piagam':
					$jenis_berkas_id = 5;
					break;
				default:
					$jenis_berkas_id = 8;
					break;
			}

			return response(['msg' => $msg, 'filename' => "/assets/berkas/".$name, 'jenis' => $jenis, 'jenis_berkas_id' => $jenis_berkas_id]);

            // $execute = DB::connection('sqlsrv_2')->table('pengguna')->where('pengguna_id','=',$pengguna_id)->update([
            //     $jenis => "/assets/berkas/".$name
            // ]);

            // if($execute){
            //     return response(['msg' => $msg, 'filename' => "/assets/berkas/".$name, 'jenis' => $jenis]);
            // }
        }

    }

	public function hapusSekolahPilihan(Request $request){
		$calon_pd = DB::connection('sqlsrv_2')
		->table('ppdb.pilihan_sekolah')
		->where('calon_peserta_didik_id', '=', $request->input('calon_peserta_didik_id'))
		->where('sekolah_id', '=', $request->input('sekolah_id'))
		->update(['soft_delete' => 1]);

        return response([ 'rows' => $calon_pd ], 201);
	}
	
	public function getBerkasCalon(Request $request){
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;
	
		$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.berkas_calon')
			->where('ppdb.berkas_calon.calon_peserta_didik_id','=', $calon_peserta_didik_id)
			->where('ppdb.berkas_calon.soft_delete','=',0)
			->select(
				'ppdb.berkas_calon.*'
			)
			->get();
		
			return response([ 
				'rows' => $fetch_cek,
				'count' => sizeof($fetch_cek)
			], 201);
	}
	
	public function getKonfirmasiPendaftaran(Request $request){
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;
	
		$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.konfirmasi_pendaftaran')
			->where('ppdb.konfirmasi_pendaftaran.calon_peserta_didik_id','=', $calon_peserta_didik_id)
			->where('ppdb.konfirmasi_pendaftaran.soft_delete','=',0)
			->select(
				'ppdb.konfirmasi_pendaftaran.*'
			)
			->get();
		
			return response([ 
				'rows' => $fetch_cek,
				'count' => sizeof($fetch_cek)
			], 201);
	}

	public function getSekolahPilihan(Request $request){
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;
	
		$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.pilihan_sekolah')
			->join('ppdb.sekolah as sekolah','sekolah.sekolah_id','=','ppdb.pilihan_sekolah.sekolah_id')
			->leftJoin('ref.mst_wilayah AS kec', 'kec.kode_wilayah', '=', DB::raw("LEFT(sekolah.kode_wilayah,6)"))
			->leftJoin('ref.mst_wilayah AS kab', 'kab.kode_wilayah', '=', 'kec.mst_kode_wilayah')
			->leftJoin('ref.mst_wilayah AS prov', 'prov.kode_wilayah', '=', 'kab.mst_kode_wilayah')
			// ->where('sekolah_id','=', $sekolah_pilihan[$i])
			->where('ppdb.pilihan_sekolah.calon_peserta_didik_id','=', $calon_peserta_didik_id)
			->where('ppdb.pilihan_sekolah.soft_delete','=',0)
			->select(
				'ppdb.pilihan_sekolah.*',
				'sekolah.nama',
				'sekolah.npsn',
				'sekolah.bentuk_pendidikan_id',
				'sekolah.status_sekolah',
				'sekolah.alamat_jalan as alamat',
				'kec.nama as kecamatan',
				'kab.nama as kabupaten',
				'prov.nama as provinsi'
			)
			->orderBy('urut_pilihan','ASC')
			->get();
		
			return response([ 
				'rows' => $fetch_cek,
				'count' => sizeof($fetch_cek)
			], 201);
	}

	public function simpanKonfirmasiPendaftaran(Request $request){
		$pengguna_id = $request->input('pengguna_id') ? $request->input('pengguna_id') : null;
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;
		$status = $request->input('status') ? json_decode($request->input('status')) : "0";

		$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.konfirmasi_pendaftaran')
			->where('calon_peserta_didik_id','=', $calon_peserta_didik_id)
			// ->where('calon_peserta_didik_id','=', $berkas_calon[$i]->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->get();

		if(sizeof($fetch_cek) > 0){
			//update
			
			$exe = DB::connection('sqlsrv_2')->table('ppdb.konfirmasi_pendaftaran')
			->where('calon_peserta_didik_id','=', $calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->update([
				'status' => $status,
				'last_update' => DB::raw('now()::timestamp(0)')
			]);

		}else{
			//insert
			$arrValue = [
				'konfirmasi_pendaftaran_id' => Str::uuid(),
				'calon_peserta_didik_id' => $calon_peserta_didik_id,
				'pengguna_id' => $pengguna_id,
				'status' => $status,
				'periode_kegiatan_id' => '2020',
				'create_date' => DB::raw('now()::timestamp(0)'),
				'last_update' => DB::raw('now()::timestamp(0)'),
				'soft_delete' => 0,
			];

			$exe = DB::connection('sqlsrv_2')->table('ppdb.konfirmasi_pendaftaran')->insert($arrValue);

			if($exe){
				return response([ 'success' => true ], 201);
			}else{
				return response([ 'success' => false ], 201);
			}
		}
		
		
	}

	public function simpanSekolahPilihan(Request $request){
		$jalur_id = $request->input('jalur_id') ? $request->input('jalur_id') : null;
		$sekolah_pilihan = $request->input('sekolah_pilihan') ? $request->input('sekolah_pilihan') : null;
		$obj_sekolah_pilihan = $request->input('obj_sekolah_pilihan') ? $request->input('obj_sekolah_pilihan') : null;
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;

		$berhasil = 0;
		$gagal = 0;
		$lewat = 0;

		for ($i=0; $i < sizeof($obj_sekolah_pilihan); $i++) { 

			$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.pilihan_sekolah')
			->where('sekolah_id','=', $obj_sekolah_pilihan[$i]['sekolah_id'])
			->where('calon_peserta_didik_id','=', $calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->get();

			if(sizeof($fetch_cek) > 0){
				//update
				//sementara ini do nothing
				$arrValue = [
					'jalur_id' => $jalur_id,
					'urut_pilihan' => $obj_sekolah_pilihan[$i]['urut_pilihan'],
					'last_update' => DB::raw('now()::timestamp(0)'),
					'soft_delete' => 0
				];

				$exe = DB::connection('sqlsrv_2')
				->table('ppdb.pilihan_sekolah')
				->where('sekolah_id','=', $obj_sekolah_pilihan[$i]['sekolah_id'])
				->where('calon_peserta_didik_id','=', $calon_peserta_didik_id)
				->update($arrValue);

				$lewat++;
			}else{
				//insert
				$arrValue = [
					'pilihan_sekolah_id' => Str::uuid(),
					'sekolah_id' => $obj_sekolah_pilihan[$i]['sekolah_id'],
					'calon_peserta_didik_id' => $calon_peserta_didik_id,
					'jalur_id' => $jalur_id,
					'urut_pilihan' => $obj_sekolah_pilihan[$i]['urut_pilihan'],
					'create_date' => DB::raw('now()::timestamp(0)'),
					'last_update' => DB::raw('now()::timestamp(0)'),
					'soft_delete' => 0,
					'periode_kegiatan_id' => '2020'
				];

				$exe = DB::connection('sqlsrv_2')->table('ppdb.pilihan_sekolah')->insert($arrValue);

				if($exe){
					$berhasil++;
				}else{
					$gagal++;
				}
			}
			
		}

		return response([ 'berhasil' => $berhasil, 'gagal' => $gagal, 'lewat' => $lewat ], 201);
	}

	public function simpanBerkasCalon(Request $request){
		$berkas_calon = $request->input('berkas_calon') ? json_decode($request->input('berkas_calon')) : null;

		$berhasil = 0;
		$gagal = 0;
		$lewat = 0;

		for ($i=0; $i < sizeof($berkas_calon); $i++) { 

			$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.berkas_calon')
			->where('jenis_berkas_id','=', $berkas_calon[$i]->jenis_berkas_id)
			->where('calon_peserta_didik_id','=', $berkas_calon[$i]->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->get();

			if(sizeof($fetch_cek) > 0){
				//update
				
				$exe = DB::connection('sqlsrv_2')->table('ppdb.berkas_calon')
				->where('jenis_berkas_id','=', $berkas_calon[$i]->jenis_berkas_id)
				->where('calon_peserta_didik_id','=', $berkas_calon[$i]->calon_peserta_didik_id)
				->where('soft_delete','=',0)
				->update([
					'nama_file' => $berkas_calon[$i]->nama_file
				]);

				$lewat++;
			}else{
				//insert
				$arrValue = [
					'berkas_calon_id' => Str::uuid(),
					'calon_peserta_didik_id' => $berkas_calon[$i]->calon_peserta_didik_id,
					'jenis_berkas_id' => $berkas_calon[$i]->jenis_berkas_id,
					'nama_file' => $berkas_calon[$i]->nama_file,
					'keterangan' => $berkas_calon[$i]->keterangan,
					'create_date' => DB::raw('now()::timestamp(0)'),
					'last_update' => DB::raw('now()::timestamp(0)'),
					'soft_delete' => 0,
					'periode_kegiatan_id' => '2020'
				];

				$exe = DB::connection('sqlsrv_2')->table('ppdb.berkas_calon')->insert($arrValue);

				if($exe){
					$berhasil++;
				}else{
					$gagal++;
				}
			}
			
		}

		return response([ 'berhasil' => $berhasil, 'gagal' => $gagal, 'lewat' => $lewat ], 201);
	}

	public function importDariPesertaDidikDapodik(Request $request)
	{
		$peserta_didik_id = $request->input('peserta_didik_id');
		$pengguna_id = $request->input('pengguna_id');

		$fetch_data = DB::connection('sqlsrv_2')->table('ppdb.peserta_didik')
		->where('peserta_didik_id','=',$peserta_didik_id)->get();

		if(sizeof($fetch_data) > 0){

			$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.calon_peserta_didik')
			->where('calon_peserta_didik_id','=', $fetch_data[0]->peserta_didik_id)
			// ->where('soft_delete','=', 0)
			->get();

			if(sizeof($fetch_cek) > 0){
				//update
				$arrValue = [
					'last_update' => DB::raw('now()::timestamp(0)'),
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
					'orang_tua_utama' => 'ayah',
					'pengguna_id' => $pengguna_id
				];

				$exe = DB::connection('sqlsrv_2')
				->table('ppdb.calon_peserta_didik')
				->where('calon_peserta_didik_id','=',$fetch_data[0]->peserta_didik_id)
				->update($arrValue);

			}else{
				//insert
				$arrValue = [
					'calon_peserta_didik_id' => $fetch_data[0]->peserta_didik_id,
					'create_date' => DB::raw('now()::timestamp(0)'),
					'last_update' => DB::raw('now()::timestamp(0)'),
					'soft_delete' => 0,
					'pengguna_id' => $pengguna_id,
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

	public function simpanLintangBujur(Request $request){
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;
		$lintang = $request->input('lintang') ? $request->input('lintang') : null;
		$bujur = $request->input('bujur') ? $request->input('bujur') : null;

		$exe = DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')
			->where('calon_peserta_didik_id','=',$calon_peserta_didik_id)
			->update([
				'lintang' => $lintang,
				'bujur' => $bujur,
				'last_update' => DB::raw('now()::timestamp(0)')
			]);
		
			return response([ 'success' => true, 'peserta_didik_id' => ($calon_peserta_didik_id),'rows' => DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')->where('calon_peserta_didik_id','=', ($calon_peserta_didik_id))->get() ], 201);
	}

	public function store(Request $request){

		// return $request->input('')
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;

		$fetch_cek = DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')->where('calon_peserta_didik_id','=',$calon_peserta_didik_id)->get();

		if(sizeof($fetch_cek) > 0){
			//update
			$label = 'update';

			$arrValue = [
				"last_update" => DB::raw('now()::timestamp(0)'), 
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
				"desa_kelurahan" => $request->input('desa_kelurahan'), 
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
				"create_date" => DB::raw('now()::timestamp(0)'), 
				"last_update" => DB::raw('now()::timestamp(0)'), 
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
				"desa_kelurahan" => $request->input('desa_kelurahan'), 
				"nama" => $request->input('nama'), 
				"nisn" => $request->input('nisn'), 
				"pendidikan_terakhir_id_wali" => $request->input('pendidikan_terakhir_id_wali')
			];

			$label = 'insert';
			$exe = DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')->insert($arrValue);
		}

		if($exe){
			return response([ 'success' => true, 'peserta_didik_id' => ($calon_peserta_didik_id ? $calon_peserta_didik_id : $pd_id),'rows' => DB::connection('sqlsrv_2')->table('ppdb.calon_peserta_didik')->where('calon_peserta_didik_id','=', ($calon_peserta_didik_id ? $calon_peserta_didik_id : $pd_id))->get() ], 201);
		}else{
			return response([ 'success' => false, 'peserta_didik_id' => null ], 201);
		}

		// return $label;
	}

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
    			'prop.nama AS provinsi',
    			'pddk_trkh_ayah.nama AS pendidikan_terakhir_ayah',
    			'pddk_trkh_ayah.nama AS pendidikan_terakhir_ibu',
    			'pddk_trkh_ayah.nama AS pendidikan_terakhir_wali',
    			'work_ayah.nama AS pekerjaan_ayah',
    			'work_ibu.nama AS pekerjaan_ibu',
    			'work_wali.nama AS pekerjaan_wali'
    		)
    		->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.calon_peserta_didik.asal_sekolah_id', '=', 'sekolah.sekolah_id')
    		->join('ref.mst_wilayah AS  kec', 'ppdb.calon_peserta_didik.kode_wilayah_kecamatan', '=', 'kec.kode_wilayah')
    		->join('ref.mst_wilayah AS  kab', 'ppdb.calon_peserta_didik.kode_wilayah_kabupaten', '=', 'kab.kode_wilayah')
    		->join('ref.mst_wilayah AS  prop', 'ppdb.calon_peserta_didik.kode_wilayah_provinsi', '=', 'prop.kode_wilayah')
    		->leftJoin('ref.pendidikan_terakhir AS pddk_trkh_ayah', 'ppdb.calon_peserta_didik.pendidikan_terakhir_id_ayah', '=', 'pddk_trkh_ayah.pendidikan_terakhir_id')
    		->leftJoin('ref.pendidikan_terakhir AS pddk_trkh_ibu', 'ppdb.calon_peserta_didik.pendidikan_terakhir_id_ibu', '=', 'pddk_trkh_ibu.pendidikan_terakhir_id')
    		->leftJoin('ref.pendidikan_terakhir AS pddk_trkh_wali', 'ppdb.calon_peserta_didik.pendidikan_terakhir_id_wali', '=', 'pddk_trkh_wali.pendidikan_terakhir_id')
    		->leftJoin('ref.pekerjaan AS work_ayah', 'ppdb.calon_peserta_didik.pekerjaan_id_ayah', '=', 'work_ayah.pekerjaan_id')
    		->leftJoin('ref.pekerjaan AS work_ibu', 'ppdb.calon_peserta_didik.pekerjaan_id_ayah', '=', 'work_ibu.pekerjaan_id')
    		->leftJoin('ref.pekerjaan AS work_wali', 'ppdb.calon_peserta_didik.pekerjaan_id_ayah', '=', 'work_wali.pekerjaan_id')
    		->first();

    	$pilihan_sekolah = PilihanSekolah::where('pilihan_sekolah.calon_peserta_didik_id', $id)
			->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
			->leftJoin('ref.jalur AS jalur', 'ppdb.pilihan_sekolah.jalur_id', '=', 'jalur.jalur_id')
			->leftJoin(
				DB::raw('(
					SELECT ROW_NUMBER
					() OVER (
						PARTITION BY pilihan_sekolah.jalur_id 
					ORDER BY
						pilihan_sekolah.urut_pilihan ASC,
						COALESCE ( konf.status, 0 ) DESC,
						konf.last_update ASC,	
						pilihan_sekolah.create_date ASC
					) AS urutan,
					urut_pilihan,
					COALESCE ( konf.status, 0 ) AS konfirmasi,
					konf.last_update,
					pilihan_sekolah.create_date,
					pilihan_sekolah.jalur_id,
					calon_peserta_didik.nama,
					pilihan_sekolah.sekolah_id,
					pilihan_sekolah.calon_peserta_didik_id
				FROM
					ppdb.pilihan_sekolah
					LEFT JOIN ppdb.konfirmasi_pendaftaran konf ON konf.calon_peserta_didik_id = pilihan_sekolah.calon_peserta_didik_id
					JOIN ppdb.calon_peserta_didik ON calon_peserta_didik.calon_peserta_didik_id = pilihan_sekolah.calon_peserta_didik_id 
				WHERE
					pilihan_sekolah.soft_delete = 0 
					AND calon_peserta_didik.soft_delete = 0 
				ORDER BY
					pilihan_sekolah.urut_pilihan ASC,
					COALESCE ( konf.status, 0 ) DESC,
					konf.last_update ASC,
					pilihan_sekolah.create_date ASC
				) as urutan'), function ($join) {
				$join->on('urutan.sekolah_id', '=', 'ppdb.pilihan_sekolah.sekolah_id');
				$join->on('urutan.calon_peserta_didik_id','=','ppdb.pilihan_sekolah.calon_peserta_didik_id');
			})
			->leftJoin('ppdb.kuota_sekolah as kuota','kuota.sekolah_id','=','ppdb.pilihan_sekolah.sekolah_id')
			->select(
				'pilihan_sekolah.*',
				'sekolah.npsn AS npsn',
				'sekolah.nama AS nama_sekolah',
				'jalur.nama AS nama_jalur',
				'urutan.urutan',
				'kuota.kuota'
			)
			->where('ppdb.pilihan_sekolah.soft_delete', 0)
			->orderBy('urut_pilihan','ASC')
			->get();

    	if(count($pilihan_sekolah) >= 1){
			$urutan = @$pilihan_sekolah[0]->urutan;

			switch (strlen($urutan)) {
				case 1: $nol = "000"; break;
				case 2: $nol = "00"; break;
				case 3: $nol = "0"; break;
				case 4: $nol = ""; break;	
				default:
					$nol = "";
					break;
			}

			$urutan = $nol.$urutan;
		}else{
			$urutan = "0000";
		}

    	$file = public_path('template_formulir_pendaftaran.rtf');

    	$orangtua = $calon_pd->orang_tua_utama;
		
		$array = array(
			'#no1'			=> substr($urutan, 0, 1),
			'#no2'			=> substr($urutan, 1, 1),
			'#no3'			=> substr($urutan, 2, 1),
			'#no4'			=> substr($urutan, 3, 1),
			'#nama' 		=> $calon_pd->nama,
			'#nik' 			=> $calon_pd->nik,
			'#jenis_kelamin' => $calon_pd->jenis_kelamin == 'L' ? 'Laki - laki' : $calon_pd->jenis_kelamin == 'P' ? 'Perempuan' : '',
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
			'#jalur' 		=> @$pilihan_sekolah[0]->nama_jalur,
			'#npsn1' 		=> @$pilihan_sekolah[0]->npsn,
			'#sekolah1' 	=> @$pilihan_sekolah[0]->nama_sekolah,
			'#npsn2' 		=> @$pilihan_sekolah[1]->npsn,
			'#sekolah2' 	=> @$pilihan_sekolah[1]->nama_sekolah,
			'#npsn3' 		=> @$pilihan_sekolah[2]->npsn,
			'#sekolah3' 	=> @$pilihan_sekolah[2]->nama_sekolah,
			'#orang_tua_utama' 			=> $calon_pd['nama_'.$orangtua],
			'#orang_tua_tempat_lahir' 	=> $calon_pd->tempat_lahir_ayah,
			'#ort_t_d' 		=> date("d", strtotime( $calon_pd['tanggal_lahir_'.$orangtua] )),
			'#ort_t_m' 		=> date("m", strtotime( $calon_pd['tanggal_lahir_'.$orangtua] )),
			'#ort_t_y' 		=> date("Y", strtotime( $calon_pd['tanggal_lahir_'.$orangtua] )),
			'#orang_tua_pendidikan'	 			=> $calon_pd['pendidikan_terakhir_'.$orangtua],
			'#orang_tua_pekerjaan' 				=> $calon_pd['pekerjaan_'.$orangtua],
			'#orang_tua_alamat_tempat_tinggal' 	=> $calon_pd['alamat_tempat_tinggal_'.$orangtua],
			'#orang_tua_no_telepon' 			=> $calon_pd['no_telepon_'.$orangtua],
			'#datenow' 		=> date("M Y")
		);

		$nama_file = 'Formulir_PPDB_2019.doc';

		// return $array; die;
		
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

    	$pilihan_sekolah = PilihanSekolah::where('pilihan_sekolah.calon_peserta_didik_id', $id)
			->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.pilihan_sekolah.sekolah_id', '=', 'sekolah.sekolah_id')
			->leftJoin('ref.jalur AS jalur', 'ppdb.pilihan_sekolah.jalur_id', '=', 'jalur.jalur_id')
			->leftJoin(
				DB::raw('(
					SELECT ROW_NUMBER
					() OVER (
						PARTITION BY pilihan_sekolah.jalur_id 
					ORDER BY
						pilihan_sekolah.urut_pilihan ASC,
						COALESCE ( konf.status, 0 ) DESC,
						konf.last_update ASC,	
						pilihan_sekolah.create_date ASC
					) AS urutan,
					urut_pilihan,
					COALESCE ( konf.status, 0 ) AS konfirmasi,
					konf.last_update,
					pilihan_sekolah.create_date,
					pilihan_sekolah.jalur_id,
					calon_peserta_didik.nama,
					pilihan_sekolah.sekolah_id,
					pilihan_sekolah.calon_peserta_didik_id
				FROM
					ppdb.pilihan_sekolah
					LEFT JOIN ppdb.konfirmasi_pendaftaran konf ON konf.calon_peserta_didik_id = pilihan_sekolah.calon_peserta_didik_id
					JOIN ppdb.calon_peserta_didik ON calon_peserta_didik.calon_peserta_didik_id = pilihan_sekolah.calon_peserta_didik_id 
				WHERE
					pilihan_sekolah.soft_delete = 0 
					AND calon_peserta_didik.soft_delete = 0 
				ORDER BY
					pilihan_sekolah.urut_pilihan ASC,
					COALESCE ( konf.status, 0 ) DESC,
					konf.last_update ASC,
					pilihan_sekolah.create_date ASC
				) as urutan'), function ($join) {
				$join->on('urutan.sekolah_id', '=', 'ppdb.pilihan_sekolah.sekolah_id');
				$join->on('urutan.calon_peserta_didik_id','=','ppdb.pilihan_sekolah.calon_peserta_didik_id');
			})
			->leftJoin('ppdb.kuota_sekolah as kuota','kuota.sekolah_id','=','ppdb.pilihan_sekolah.sekolah_id')
			->select(
				'pilihan_sekolah.*',
				'sekolah.npsn AS npsn',
				'sekolah.nama AS nama_sekolah',
				'jalur.nama AS nama_jalur',
				'urutan.urutan',
				'kuota.kuota'
			)
			->where('ppdb.pilihan_sekolah.soft_delete', 0)
			->orderBy('urut_pilihan','ASC')
			->get();

		if(count($pilihan_sekolah) >= 1){
			$urutan = @$pilihan_sekolah[0]->urutan;

			switch (strlen($urutan)) {
				case 1: $nol = "000"; break;
				case 2: $nol = "00"; break;
				case 3: $nol = "0"; break;
				case 4: $nol = ""; break;	
				default:
					$nol = "";
					break;
			}

			$urutan = $nol.$urutan;
		}else{
			$urutan = "0000";
		}

  //   	$file = public_path('template_bukti_pendaftaran.rtf');
		
		// $array = array(
		// 	'#no1'			=> substr($urutan, 0, 1),
		// 	'#no2'			=> substr($urutan, 1, 1),
		// 	'#no3'			=> substr($urutan, 2, 1),
		// 	'#no4'			=> substr($urutan, 3, 1),
		// 	'#nik' 			=> $calon_pd->nik,
		// 	'#nama' 		=> $calon_pd->nama,
		// 	'#nisn' 		=> $calon_pd->nisn,
		// 	'#tempat_lahir' => $calon_pd->tempat_lahir,
		// 	'#tgllhr_d' 	=> date("d", strtotime($calon_pd->tanggal_lahir)),
		// 	'#tgllhr_m' 	=> date("m", strtotime($calon_pd->tanggal_lahir)),
		// 	'#tgllhr_y' 	=> date("Y", strtotime($calon_pd->tanggal_lahir)),
		// 	'#lintang' 		=> $calon_pd->lintang,
		// 	'#bujur' 		=> $calon_pd->bujur,
		// 	'#asal_sekolah' => $calon_pd->asal_sekolah,
		// 	'#jalur' 		=> @$pilihan_sekolah[0]->nama_jalur,
		// 	'#npsn1' 		=> @$pilihan_sekolah[0]->npsn,
		// 	'#sekolah1' 	=> @$pilihan_sekolah[0]->nama_sekolah,
		// 	'#npsn2' 		=> @$pilihan_sekolah[1]->npsn,
		// 	'#sekolah2' 	=> @$pilihan_sekolah[1]->nama_sekolah,
		// 	'#npsn3' 		=> @$pilihan_sekolah[2]->npsn,
		// 	'#sekolah3' 	=> @$pilihan_sekolah[2]->nama_sekolah,
		// 	'#datenow' 		=> date("F Y"),
		// 	'#codeQR' 		=> '',
		// );

		// $nama_file = 'Bukti_PPDB_2019.doc';

		// // return $array; die;
		
		// return WordTemplate::export($file, $array, $nama_file);

		$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template_bukti_pendaftaran.docx');

        $templateProcessor->setValue('no1', substr($urutan, 0, 1));
		$templateProcessor->setValue('no2', substr($urutan, 1, 1));
		$templateProcessor->setValue('no3', substr($urutan, 2, 1));
		$templateProcessor->setValue('no4', substr($urutan, 3, 1));
		$templateProcessor->setValue('nik', $calon_pd->nik);
		$templateProcessor->setValue('nama', $calon_pd->nama);
		$templateProcessor->setValue('nisn', $calon_pd->nisn);
		$templateProcessor->setValue('tempat_lahir', $calon_pd->tempat_lahir);
		$templateProcessor->setValue('tgllhr_d', date("d", strtotime($calon_pd->tanggal_lahir)));
		$templateProcessor->setValue('tgllhr_m', date("m", strtotime($calon_pd->tanggal_lahir)));
		$templateProcessor->setValue('tgllhr_y', date("Y", strtotime($calon_pd->tanggal_lahir)));
		$templateProcessor->setValue('lintang', $calon_pd->lintang);
		$templateProcessor->setValue('bujur', $calon_pd->bujur);
		$templateProcessor->setValue('asal_sekolah', $calon_pd->asal_sekolah);
		$templateProcessor->setValue('jalur', @$pilihan_sekolah[0]->nama_jalur);
		$templateProcessor->setValue('npsn1', @$pilihan_sekolah[0]->npsn);
		$templateProcessor->setValue('sekolah1', @$pilihan_sekolah[0]->nama_sekolah);
		$templateProcessor->setValue('npsn2', @$pilihan_sekolah[1]->npsn);
		$templateProcessor->setValue('sekolah2', @$pilihan_sekolah[1]->nama_sekolah);
		$templateProcessor->setValue('npsn3', @$pilihan_sekolah[2]->npsn);
		$templateProcessor->setValue('sekolah3', @$pilihan_sekolah[2]->nama_sekolah);
		$templateProcessor->setValue('datenow', date("F Y"));
        $templateProcessor->setImageValue('codeQR', array('path' => "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={$calon_pd->nik}", 'width' => '1in', 'height' => '1in'));


        // $templateProcessor->deleteBlock('DELETEME');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="Bukti_PPDB_2019.docx"');
        $templateProcessor->saveAs('php://output');
}
}
