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
	public function daftarPesertaDidikDiterima(Request $request){
		$sekolah_id = $request->input('sekolah_id') ? $request->input('sekolah_id') : null;
		$searchText = $request->input('searchText') ? $request->input('searchText') : null;

		if($searchText){
			$param_keyword = " AND (calon.nama ilike '%{$searchText}%' OR calon.nisn ilike '%{$searchText}%' OR calon.nik ilike '%{$searchText}%')";
		}else{
			$param_keyword = "";
		}

		$sql = "select 
					*,
					COALESCE(pas_foto.nama_file, '/assets/img/generic-avatar.png') as pas_foto,
					calon.nik,
					calon.nisn,
					calon.tempat_lahir,
					calon.tanggal_lahir,
					rekap.peringkat_ppdb_tahap_1_2.nama_jalur as jalur
				from 
					rekap.peringkat_ppdb_tahap_1_2
				LEFT JOIN (
					select calon_peserta_didik_id, max(nama_file) as nama_file from ppdb.berkas_calon
					where soft_delete = 0 and jenis_berkas_id = 8 group by calon_peserta_didik_id
				) pas_foto on pas_foto.calon_peserta_didik_id = rekap.peringkat_ppdb_tahap_1_2.calon_peserta_didik_id
				JOIN ppdb.calon_peserta_didik calon on calon.calon_peserta_didik_id = rekap.peringkat_ppdb_tahap_1_2.calon_peserta_didik_id
				where 
					sekolah_id = '{$sekolah_id}'
				{$param_keyword}
				order by jalur_id, no_urut_penerimaan";

		$fetch = DB::connection('sqlsrv_2')
				->select(DB::raw($sql));

		for ($i=0; $i < sizeof($fetch); $i++) { 
			//pas foto
			// $fetch_foto = DB::connection('sqlsrv_2')
			// ->table('ppdb.berkas_calon')
			// ->where('calon_peserta_didik_id','=',$fetch[$i]->calon_peserta_didik_id)
			// ->where('soft_delete','=',0)
			// ->where('jenis_berkas_id','=',8)
			// ->get();

			// if(sizeof($fetch_foto) > 0){
			// 	$fetch[$i]->pas_foto = $fetch_foto[0]->nama_file;	
			// }else{
			// 	$fetch[$i]->pas_foto = '/assets/img/generic-avatar.png';
			// }
		}
		
		return response(
			[
				'rows' => $fetch,
				'count' => sizeof($fetch),
				'countAll' => sizeof($fetch)
			],
			200
		);
	}
	
	public function RekapKuotaSekolah(Request $request){
		$sekolah_id = $request->sekolah_id ? $request->sekolah_id : null;

		$fetch = DB::connection('sqlsrv_2')
				->select(DB::raw("SELECT
									ref.jalur.nama,
									ref.jalur.jalur_id,
									kuota_jalur_def.kuota as kuota,
									COALESCE(kuota_jalur_sekolah.jumlah,0) as jumlah,
									CASE WHEN kuota_jalur_def.kuota > 0 THEN ( COALESCE(kuota_jalur_sekolah.jumlah,0) / CAST(kuota_jalur_def.kuota as float) * 100 ) ELSE 0 END as persen,
									tanggal_rekap
								FROM
									REF.jalur 
									LEFT JOIN (
									SELECT
										jalur_id,
										sum(1) as jumlah,
										min(diterima.tanggal_rekap) as tanggal_rekap
									FROM
										rekap.peringkat_ppdb_tahap_1_2 diterima
										JOIN ppdb.calon_peserta_didik calon ON calon.calon_peserta_didik_id = diterima.calon_peserta_didik_id 
									WHERE
										sekolah_id = '{$sekolah_id}' 
									GROUP BY
										jalur_id
									) kuota_jalur_sekolah on kuota_jalur_sekolah.jalur_id = ref.jalur.jalur_id
									LEFT JOIN (
									SELECT
										jalur.jalur_id,
										COALESCE((case 
											when jalur.jalur_id = '0100' then kuota_0100
											when jalur.jalur_id = '0200' then kuota_0200
											when jalur.jalur_id = '0300' then kuota_0300
											when jalur.jalur_id = '0400' then kuota_0400
											when jalur.jalur_id = '0500' then kuota_0500
											else 0
										end),0) as kuota
									FROM
										ppdb.kuota_sekolah 
										JOIN ref.jalur jalur on 1 = 1 and jalur.level_jalur = 1
									WHERE
										sekolah_id = '{$sekolah_id}'
									) kuota_jalur_def on kuota_jalur_def.jalur_id = ref.jalur.jalur_id
								WHERE
									level_jalur = 1 
									AND expired_date IS NULL"));
		return response(
			[
				'rows' => $fetch,
				'count' => sizeof($fetch),
				'countAll' => sizeof($fetch)
			],
			200
		);
	}

	public function PeringkatPesertaDidik(Request $request)
	{
		$return = $this->PeringkatPesertaDidik__($request);

		return Response($return, 200);
	}

	public function PeringkatPesertaDidik_excel(Request $request)
	{
		if(!$request->sekolah_id) return ['params' => false];

		$rows = $this->PeringkatPesertaDidik__($request);
		$sekolah = DB::connection('sqlsrv_2')
			->table("ppdb.sekolah AS sekolah")
			->select('sekolah.*', 'kuota_sekolah.kuota')
			->leftJoin('ppdb.kuota_sekolah AS kuota_sekolah', 'sekolah.sekolah_id', '=', 'kuota_sekolah.sekolah_id')
			->where('sekolah.sekolah_id', $request->sekolah_id)
			->first();

		if(!$sekolah) return ['status' => 'sekolah tidak ditemukan'];
		$return = ['rows' => $rows, 'sekolah' => $sekolah];
		return view('excel/PPDB_calonPesertaDidik_terima', $return);
	}

	public function PeringkatPesertaDidik__($request){
		$sekolah_id = $request->sekolah_id ? $request->sekolah_id : null;
		$limit = $request->limit ? $request->limit : 20;
		$start = $request->start ? $request->start : 0;
		$urut = $request->urut ? $request->urut : 'jarak_asc';
		$jalur_id = $request->jalur_id ? $request->jalur_id : null;
		$searchText = $request->searchText ? $request->searchText : null;
		$tipe = $request->tipe ? $request->tipe : 'terima';

		if($jalur_id){
			$param_jalur = " AND diterima.jalur_id = '".$jalur_id."'";
		}else{
			$param_jalur = "";
		}

		if($searchText){
			$param_keyword = " AND (calon.nama ilike '%".$searchText."%' OR calon.nisn ilike '%".$searchText."%' OR calon.nik ilike '%".$searchText."%')";
		}else{
			$param_keyword = "";
		}

		if($tipe == 'terima'){

			$fetch = DB::connection('sqlsrv_2')
					->select(DB::raw("SELECT
										diterima.*,
										calon.*,
										pengguna.nama as pendaftar,
										pengguna.username as email_pendaftar 
									FROM
										rekap.peringkat_ppdb_tahap_1_2 diterima
									JOIN ppdb.calon_peserta_didik calon ON calon.calon_peserta_didik_id = diterima.calon_peserta_didik_id 
									LEFT JOIN pengguna on pengguna.pengguna_Id = calon.pengguna_id
									WHERE
										diterima.sekolah_id = '{$sekolah_id}'
									{$param_jalur}
									{$param_keyword}
									ORDER BY
										".($urut == 'jarak_asc' ? "diterima.jarak asc" : ($urut == 'jarak_desc' ? "diterima.jarak desc" : ($urut == 'nama_asc' ? "diterima.nama asc" : ($urut == 'nama_desc' ? "diterima.nama desc" : "diterima.jarak asc"))))."
									OFFSET {$start} LIMIT {$limit}
									"));
			
			$fetch_count = DB::connection('sqlsrv_2')
						->select(DB::raw("SELECT
											sum(1) as total
										FROM
											rekap.peringkat_ppdb_tahap_1_2 diterima
										JOIN ppdb.calon_peserta_didik calon ON calon.calon_peserta_didik_id = diterima.calon_peserta_didik_id 
										WHERE
											diterima.sekolah_id = '{$sekolah_id}'
										{$param_jalur}
										{$param_keyword}
										"));

		}else{
			$fetch = DB::connection('sqlsrv_2')
					->select(DB::raw("SELECT
										(CASE 
											WHEN CAST(pd_diterima.status_terima as varchar(10)) IS NOT NULL THEN (select ref.status_terima.nama from ref.status_terima where ref.status_terima.status_terima_id = pd_diterima.status_terima)
											ELSE (CASE
												WHEN diterima.sekolah_id IS NUll THEN 'Belum diverifikasi'
												WHEN diterima.sekolah_id = pilihan.sekolah_id THEN 'Diterima di sekolah ini'
												WHEN diterima.sekolah_id != pilihan.sekolah_id THEN 'Diterima di sekolah lain'
											END)
										END) as status,
										diterima.sekolah_id AS sekolah_id_penerima,
										diterima.sekolah_penerima,
										pilihan.sekolah_id,
										pengguna.nama as pendaftar,
										pengguna.username as email_pendaftar,
										pilihan.*,
										calon.*	
									FROM
										ppdb.pilihan_sekolah pilihan
										LEFT JOIN rekap.peringkat_ppdb_tahap_1_2 diterima ON diterima.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
										LEFT JOIN ppdb.calon_peserta_didik calon on calon.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
										LEFT JOIN ppdb.peserta_didik_diterima pd_diterima on pd_diterima.peserta_didik_id = calon.calon_peserta_didik_id
										AND pd_diterima.soft_delete = 0 
										AND pd_diterima.periode_kegiatan_id = calon.periode_kegiatan_id
										AND pd_diterima.status_terima != 0
										LEFT JOIN pengguna on pengguna.pengguna_id = calon.pengguna_id
									WHERE
										pilihan.sekolah_id = '{$sekolah_id}'
									{$param_jalur}
									{$param_keyword}
									AND (
										diterima.sekolah_id IS NUll
										OR diterima.sekolah_id != pilihan.sekolah_id
									)
									ORDER BY
										".($urut == 'jarak_asc' ? "diterima.jarak asc" : ($urut == 'jarak_desc' ? "diterima.jarak desc" : ($urut == 'nama_asc' ? "diterima.nama asc" : ($urut == 'nama_desc' ? "diterima.nama desc" : "diterima.jarak asc"))))."
									OFFSET {$start} LIMIT {$limit}
									"));
			
			$fetch_count = DB::connection('sqlsrv_2')
						->select(DB::raw("SELECT
											sum(1) as total
										FROM
											ppdb.pilihan_sekolah pilihan
											LEFT JOIN rekap.peringkat_ppdb_tahap_1_2 diterima ON diterima.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
											LEFT JOIN ppdb.calon_peserta_didik calon on calon.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
										WHERE
											pilihan.sekolah_id = '{$sekolah_id}'
										{$param_jalur}
										{$param_keyword}
										AND (
											diterima.sekolah_id IS NUll
											OR diterima.sekolah_id != pilihan.sekolah_id
										)
										"));
		}


		for ($i=0; $i < sizeof($fetch); $i++) { 
			//pas foto
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.berkas_calon')
			->where('calon_peserta_didik_id','=',$fetch[$i]->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->where('jenis_berkas_id','=',8)
			->get();

			if(sizeof($fetch_foto) > 0){
				$fetch[$i]->pas_foto = $fetch_foto[0]->nama_file;
			}else{
				$fetch[$i]->pas_foto = '/assets/img/generic-avatar.png';
			}

			//konfirmasi
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.konfirmasi_pendaftaran')
			->where('calon_peserta_didik_id','=',$fetch[$i]->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			// ->where('jenis_berkas_id','=',8)
			->get();

			if(sizeof($fetch_foto) > 0){
				$fetch[$i]->status_konfirmasi = $fetch_foto[0]->status;
				$fetch[$i]->tanggal_konfirmasi = $fetch_foto[0]->last_update;
			}else{
				$fetch[$i]->status_konfirmasi = 0;
				$fetch[$i]->tanggal_konfirmasi = '-';
			}

			//sekolah_asal
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.sekolah')
			->where('sekolah_id','=',$fetch[$i]->asal_sekolah_id)
			->where('soft_delete','=',0)
			->get();

			if(sizeof($fetch_foto) > 0){
				$fetch[$i]->sekolah_asal = $fetch_foto[0];
				// $fetch[$i]->tingkat_pendidikan_id = $fetch_foto[0];
			}else{
				$fetch[$i]->sekolah_asal = [];
			}

			//peserta didik diterima
			$fetch_diterima = DB::connection('sqlsrv_2')
			->table('ppdb.peserta_didik_diterima')
			->join('ppdb.sekolah as sekolah','sekolah.sekolah_id','=','ppdb.peserta_didik_diterima.sekolah_id')
			->where('peserta_didik_id','=',$fetch[$i]->calon_peserta_didik_id)
			->where('ppdb.peserta_didik_diterima.soft_delete','=',0)
			->where('ppdb.peserta_didik_diterima.periode_kegiatan_id','=','2020')
			// ->whereNotIn('status_terima', array('0'))
			->select(
				'ppdb.peserta_didik_diterima.*',
				'sekolah.nama as nama_sekolah'
			);

			// if($tipe != 'terima'){
			// 	$fetch_diterima->whereNotIn('status_terima',array('1'));
			// }else{
			// 	$fetch_diterima->whereIn('status_terima',array('1'));
			// }

			$fetch_diterima = $fetch_diterima->get();

			if(sizeof($fetch_diterima) > 0){
				$fetch[$i]->status_terima = $fetch_diterima[0]->status_terima;
				$fetch[$i]->verifikator = $fetch_diterima[0]->nama_sekolah;
			}else{
				$fetch[$i]->status_terima = null;
				$fetch[$i]->verifikator = null;
			}

			//sekolah_asal
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.sekolah')
			->where('sekolah_id','=',$fetch[$i]->asal_sekolah_id)
			->where('soft_delete','=',0)
			->get();

			if(sizeof($fetch_foto) > 0){
				$fetch[$i]->sekolah_asal = $fetch_foto[0];
				// $fetch[$i]->tingkat_pendidikan_id = $fetch_foto[0];
			}else{
				$fetch[$i]->sekolah_asal = [];
			}

		}
		

		return [
				'rows' => $fetch,
				'count' => sizeof($fetch),
				'countAll' => $fetch_count[0]->total
			];

	}

	public function simpanPesertaDidikDiterima(Request $request){
		$peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;
		$sekolah_id = $request->input('sekolah_id') ? $request->input('sekolah_id') : null;
		$status_terima = $request->input('status_terima') ? $request->input('status_terima') : null;
		$periode_kegiatan_id = $request->input('periode_kegiatan_id') ? $request->input('periode_kegiatan_id') : '2020';

		$fetch_cek = DB::connection('sqlsrv_2')
					->table('ppdb.peserta_didik_diterima')
					->where('peserta_didik_id','=',$peserta_didik_id)
					// ->where('sekolah_id','=',$sekolah_id)
					->where('periode_kegiatan_id','=',$periode_kegiatan_id)
					// ->where('soft_delete','=',0)
					->get();
		
		if(sizeof($fetch_cek) > 0){
			//sudah ada
			$exe = DB::connection('sqlsrv_2')->table('ppdb.peserta_didik_diterima')
			->where('peserta_didik_id','=',$peserta_didik_id)
			// ->where('sekolah_id','=',$sekolah_id)
			->where('periode_kegiatan_id','=',$periode_kegiatan_id)
			->update([
				'soft_delete' => 0,
				'status_terima' => $status_terima,
				'last_update' => date('Y-m-d H:i:s')
			]);
		}else{
			//belum ada
			$exe = DB::connection('sqlsrv_2')->table('ppdb.peserta_didik_diterima')
			->insert([
				'peserta_didik_id' =>  $peserta_didik_id,
				'periode_kegiatan_id' => $periode_kegiatan_id,
				'status_terima' => $status_terima,
				'pengguna_id' => $sekolah_id,
				'create_date' => DB::raw('now()::timestamp(0)'),
				'last_update' => DB::raw('now()::timestamp(0)'),
				'soft_delete' => 0,
				'sekolah_id' => $sekolah_id
			]);
		}

		if($exe){

			if((int)$status_terima != 1){
				//update konfirmasi pendaftar
				$exe = DB::connection('sqlsrv_2')
						->table('ppdb.konfirmasi_pendaftaran')
						->where('calon_peserta_didik_id','=', $peserta_didik_id)
						->where('periode_kegiatan_id','=', $periode_kegiatan_id)
						->update([
							'last_update' => DB::raw('now()::timestamp(0)'),
							'soft_delete' => 0,
							'status' => 0
						]);
			}

			return response(
				[
					'rows' => DB::connection('sqlsrv_2')->table('ppdb.peserta_didik_diterima')
								->where('peserta_didik_id','=',$peserta_didik_id)
								->where('sekolah_id','=',$sekolah_id)
								->where('periode_kegiatan_id','=',$periode_kegiatan_id)
								->where('soft_delete','=',0)
								->get(),
					'rows_konfirmasi' => DB::connection('sqlsrv_2')->table('ppdb.konfirmasi_pendaftaran')
										->where('calon_peserta_didik_id','=',$peserta_didik_id)
										->where('periode_kegiatan_id','=',$periode_kegiatan_id)
										->get(),
					'success' => true
				],
				200
			);
		}else{
			return response(
				[
					'rows' => [],
					'success' => false
				],
				201
			);
		}
	}

	public function getRekapTotal(Request $request){
		$kode_wilayah = $request->kode_wilayah ? $request->kode_wilayah : null;

		$sql = "SELECT 
					SUM ( 1 ) AS total,
					SUM ( calons.konfirmasi ) AS konfirmasi,
					SUM ( CASE WHEN calons.persen_valid < 100 THEN 0 ELSE 1 END ) AS berkas_valid,
					0 AS diterima 
				FROM
					ppdb.calon_peserta_didik
				JOIN (
				SELECT
					( CASE WHEN konfirmasi.calon_peserta_didik_id IS NOT NULL THEN 1 ELSE 0 END ) AS konfirmasi,
					validasi_berkas.jalur_id,
					validasi_berkas.persen_valid,
					calon_peserta_didik.calon_peserta_didik_id 
				FROM
					ppdb.calon_peserta_didik
					LEFT JOIN (
					SELECT
						pilihan.calon_peserta_didik_id,
						pilihan.jalur_id,
						SUM ( CASE WHEN jalur_berkas.wajib = 1 THEN 1 ELSE 0 END ) AS total,
						SUM ( CASE WHEN jalur_berkas.wajib = 1 AND berkas.nama_file IS NOT NULL THEN 1 ELSE 0 END ) AS total_valid,
						ROUND(
							CAST (
								(
								SUM ( CASE WHEN jalur_berkas.wajib = 1 AND berkas.nama_file IS NOT NULL THEN 1 ELSE 0 END ) / CAST ( SUM ( CASE WHEN jalur_berkas.wajib = 1 THEN 1 ELSE 0 END ) AS FLOAT ) * 100 
							) AS NUMERIC 
						),
						2 
						) AS persen_valid 
					FROM
						REF.jalur_berkas jalur_berkas
						JOIN ppdb.pilihan_sekolah pilihan ON pilihan.jalur_id = jalur_berkas.jalur_id 
						AND pilihan.soft_delete = 0 
						AND pilihan.urut_pilihan = 1
						LEFT JOIN ppdb.berkas_calon berkas ON berkas.calon_peserta_didik_id = pilihan.calon_peserta_didik_id 
						AND berkas.soft_delete = 0 
						AND berkas.jenis_berkas_id = jalur_berkas.jenis_berkas_id 
					WHERE
						expired_date IS NULL 
					GROUP BY
						pilihan.calon_peserta_didik_id,
						pilihan.jalur_id 
					) validasi_berkas ON validasi_berkas.calon_peserta_didik_id = calon_peserta_didik.calon_peserta_didik_id
					LEFT JOIN ( SELECT DISTINCT ( calon_peserta_didik_id ) AS calon_peserta_didik_id FROM ppdb.konfirmasi_pendaftaran WHERE soft_delete = 0 AND status = 1 ) konfirmasi ON konfirmasi.calon_peserta_didik_id = calon_peserta_didik.calon_peserta_didik_id 
				WHERE
					calon_peserta_didik.soft_delete = 0 
				) calons ON calons.calon_peserta_didik_id = calon_peserta_didik.calon_peserta_didik_id
				LEFT JOIN (
				SELECT
					calon_peserta_didik_id,
					concat ( LEFT ( sekolah.kode_wilayah, 4 ), '00' ) AS kode_kab,
					ppdb.pilihan_sekolah.jalur_id 
				FROM
					ppdb.pilihan_sekolah
					JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id 
				WHERE
					ppdb.pilihan_sekolah.soft_delete = 0 
					AND sekolah.soft_delete = 0 
					".($kode_wilayah ? "AND LEFT ( sekolah.kode_wilayah, 4 ) = '".substr($kode_wilayah,0,4)."'" : "")." 
				GROUP BY
					calon_peserta_didik_id,
					LEFT ( sekolah.kode_wilayah, 4 ),
					ppdb.pilihan_sekolah.jalur_id
				) as pilihan_sekolahnya on pilihan_sekolahnya.calon_peserta_didik_id = calon_peserta_didik.calon_peserta_didik_id
				WHERE calon_peserta_didik.Soft_delete = 0
				AND pilihan_sekolahnya.kode_kab = '{$kode_wilayah}'";

		$fetch = DB::connection('sqlsrv_2')->select(DB::raw($sql));

		return $fetch;
	}

	public function batalkanKonfirmasi(Request $request)
	{
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;

		if($calon_peserta_didik_id){

			$exe = DB::connection('sqlsrv_2')
			->table('ppdb.konfirmasi_pendaftaran')
			->where('calon_peserta_didik_id','=',$calon_peserta_didik_id)
			->update([
				'status' => 0,
				'last_update' => DB::raw('now()::timestamp(0)')
			]);

			return response(
				[
					'rows' => DB::connection('sqlsrv_2')->table('ppdb.konfirmasi_pendaftaran')->where('calon_peserta_didik_id','=',$calon_peserta_didik_id)->get(),
					'success' => ($exe ? true : false)
				],
				200
			);

		}else{
			return response(
				[
					'rows' => [],
					'success' => false
				],
				201
			);
		}
	}

	public function getCalonPesertaDidikSekolah(Request $request)
	{
		$return = $this->getCalonPesertaDidikSekolah__($request);
		return $return;
	}

	public function getCalonPesertaDidikSekolah_excel(Request $request)
	{
		$calonPD = $this->getCalonPesertaDidikSekolah__($request);
		$sekolah = DB::connection('sqlsrv_2')
			->table('ppdb.sekolah AS sekolah')
			->select(
				'sekolah.*',
				'kuota_sekolah.kuota'
			)
			->leftJoin('ppdb.kuota_sekolah AS kuota_sekolah', 'sekolah.sekolah_id', '=', 'kuota_sekolah.sekolah_id')
			->where('sekolah.sekolah_id', $request->sekolah_id)
			->where('sekolah.soft_delete', 0)
			->first();

		return view('excel/PPDB_calonPesertaDidik_sekolah', ['return' => $calonPD, 'sekolah' => $sekolah]);
	}

	public function getCalonPesertaDidikSekolah__($request){
		$limit = $request->limit ? $request->limit : 20;
	    $start = $request->start ? $request->start : 0;
		$sekolah_id = $request->sekolah_id ? $request->sekolah_id : null;
		$urut_pilihan = $request->urut_pilihan ? $request->urut_pilihan : null;
		$searchText = $request->searchText ? $request->searchText : null;
		$urut = $request->urut ? $request->urut : null;
		$jalur_id = $request->jalur_id ? $request->jalur_id : null;
		$urut_pilihan = $request->urut_pilihan ? $request->urut_pilihan : null;
		$verifikasi = $request->verifikasi ? $request->verifikasi : 'N';
		$status_terima = $request->status_terima ? $request->status_terima : null;
		// $status_konfirmasi = $request->status_konfirmasi ? $request->status_konfirmasi : null;
		
		if($searchText){
			$param_keyword = " AND (calon.nama ilike '%".$searchText."%' OR calon.nisn ilike '%".$searchText."%' OR calon.nik ilike '%".$searchText."%')";
		}else{
			$param_keyword = "";
		}

		if($urut_pilihan){
			$param_urut = " AND pilihan.urut_pilihan = '".$urut_pilihan."'";
		}else{
			$param_urut = "";
		}
		
		if($jalur_id){
			$param_jalur = " AND pilihan.jalur_id = '".$jalur_id."'";
		}else{
			$param_jalur = "";
		}
		
		if($urut_pilihan){
			$param_pilihan = " AND pilihan.urut_pilihan = '".$urut_pilihan."'";
		}else{
			$param_pilihan = "";
		}
		
		if($verifikasi == 'N'){
			$param_diterima = " AND diterima.peserta_didik_id is null";
		}else{
			$param_diterima = " AND diterima.peserta_didik_id is not null";
		}
		
		if($status_terima){
			$param_status_terima = " AND diterima.status_terima = '".$status_terima."'";
		}else{
			$param_status_terima = "";
		}
		
		// if($status_konfirmasi){
		// 	$param_konf = " AND urutan.konfirmasi = '".$status_konfirmasi."'";
		// }else{
		// 	$param_konf = "";
		// }
		// -- ppdb.calculate_distance(CAST ( (case when length(calon.lintang) > 1 then calon.lintang end) AS FLOAT ),CAST ( (case when length(calon.bujur) > 1 then calon.bujur end) AS FLOAT ), cast(sekolah.lintang as float), cast(sekolah.bujur as float), cast('Meter' as varchar(1))) ASC,
		// -- ppdb.calculate_distance(CAST ( (case when length(calon.lintang) > 1 then calon.lintang end) AS FLOAT ),CAST ( (case when length(calon.bujur) > 1 then calon.bujur end) AS FLOAT ), cast(sekolah.lintang as float), cast(sekolah.bujur as float), cast('Meter' as varchar(1))) ASC					

		if($urut == 'jarak'){
			$query_urut = " pilihan.jalur_id,
							ppdb.calculate_distance (
								( CASE WHEN LENGTH ( calon.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
								( CASE WHEN LENGTH ( calon.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
								( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
								( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
								'Meter' 
							) ASC,
							pilihan.urut_pilihan ASC,
							urutan.urutan ASC
						";
		}else{
			$query_urut = "
							pilihan.jalur_id,
							pilihan.urut_pilihan ASC,
							urutan.urutan ASC,
							ppdb.calculate_distance (
								( CASE WHEN LENGTH ( calon.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
								( CASE WHEN LENGTH ( calon.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
								( CASE WHEN LENGTH ( sekolah.lintang ) > 1 THEN CAST ( sekolah.lintang AS FLOAT ) END ),
								( CASE WHEN LENGTH ( sekolah.bujur ) > 1 THEN CAST ( sekolah.bujur AS FLOAT ) END ),
								'Meter' 
							 ) ASC
						";
		}

		$query_body = "FROM
						ppdb.pilihan_sekolah pilihan
						JOIN ppdb.calon_peserta_didik calon ON calon.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
						JOIN REF.jalur jalur ON jalur.jalur_id = pilihan.jalur_id 
						JOIN ppdb.sekolah sekolah on sekolah.sekolah_id =  pilihan.sekolah_id
						LEFT JOIN (
							SELECT ROW_NUMBER
							() OVER (
								PARTITION BY pilihan_sekolah.sekolah_id, pilihan_sekolah.jalur_id
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
							pilihan_sekolah.sekolah_id,
							pilihan_sekolah.jalur_id
						) as urutan on urutan.sekolah_id = pilihan.sekolah_id
						AND urutan.calon_peserta_didik_id = pilihan.calon_peserta_didik_id
						LEFT JOIN ppdb.peserta_didik_diterima diterima on diterima.peserta_didik_id = calon.calon_peserta_didik_id and diterima.periode_kegiatan_id = calon.periode_kegiatan_id and diterima.soft_delete = 0 and diterima.status_terima != 0
						LEFT JOIN pengguna on pengguna.pengguna_id = calon.pengguna_id
					WHERE
						pilihan.soft_delete = 0 
						AND calon.soft_delete= 0
						AND pilihan.sekolah_id = '{$sekolah_id}' 
						AND urutan.konfirmasi = 1
						{$param_keyword}
						{$param_urut}
						{$param_jalur}
						{$param_pilihan}
						{$param_diterima}
						{$param_status_terima}
						"; 
		
		// return "SELECT
		// 	jalur.nama as jalur,
		// 	calon.*,
		// 	pilihan.jalur_id,
		// 	pilihan.urut_pilihan,
		// 	urutan.*,
		// 	sekolah.lintang as lintang_sekolah, 
		// 	sekolah.bujur as bujur_sekolah
		// 	,ppdb.calculate_distance(
		// 		cast(calon.lintang as float), 
		// 		cast(calon.bujur as float), 
		// 		cast(sekolah.lintang as float), 
		// 		cast(sekolah.bujur as float), 
		// 		cast('K' as varchar(1))
		// 	) as jarak 
		// {$query_body}
		// ORDER BY
		// {$query_urut}
		// LIMIT {$limit} OFFSET {$start}";die;

		$fetch = DB::connection('sqlsrv_2')->select(DB::raw("SELECT
			jalur.nama as jalur,
			calon.*,
			pilihan.jalur_id,
			pilihan.urut_pilihan,
			urutan.*,
			sekolah.lintang as lintang_sekolah, 
			sekolah.bujur as bujur_sekolah
			,ppdb.calculate_distance(
				( CASE WHEN LENGTH ( calon.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
				( CASE WHEN LENGTH ( calon.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
				cast(sekolah.lintang as float), 
				cast(sekolah.bujur as float), 
				cast('Meter' as varchar(10))
			) as jarak
			,ppdb.calculate_distance(
				( CASE WHEN LENGTH ( calon.lintang ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.lintang,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
				( CASE WHEN LENGTH ( calon.bujur ) > 4 THEN CAST ( replace( substring(replace(REGEXP_REPLACE(concat(split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',1),'.',split_part(replace(replace(calon.bujur,'`',''),',','.'),'.',2)),'[[:alpha:]]','','g'),'\"',''),1,10), ' ', '0' ) AS FLOAT ) END ),
				cast(sekolah.lintang as float), 
				cast(sekolah.bujur as float), 
				cast('K' as varchar(1))
			) as jarak_km,
			diterima.peserta_didik_id as peserta_didik_id_diterima,
			diterima.status_terima as status_terima,
			pengguna.nama as pendaftar,
			pengguna.username as email_pendaftar,
			(case when diterima.sekolah_id = pilihan.sekolah_id then 'sekolah_sama' else 'sekolah_lain' end) as diterima_sekolah,
			(case when diterima.sekolah_id = pilihan.sekolah_id then 'sekolah_sama' else (select nama from ppdb.sekolah where sekolah_id = diterima.sekolah_id) end) as sekolah_penerima 
		{$query_body}
		ORDER BY
		{$query_urut}
		LIMIT {$limit} OFFSET {$start}"));

		// $diterima = 0;

		for ($i=0; $i < sizeof($fetch); $i++) { 
			//pas foto
			$fetch_foto = DB::connection('sqlsrv_2')
			->table('ppdb.berkas_calon')
			->where('calon_peserta_didik_id','=',$fetch[$i]->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->where('jenis_berkas_id','=',8)
			->get();

			if(sizeof($fetch_foto) > 0){
				$fetch[$i]->pas_foto = $fetch_foto[0]->nama_file;
			}else{
				$fetch[$i]->pas_foto = '/assets/img/generic-avatar.png';
			}

			//sekolah_asal
			$fetch_sekolah_asal = DB::connection('sqlsrv_2')
			->table('ppdb.sekolah')
			->where('sekolah_id','=',$fetch[$i]->asal_sekolah_id)
			->where('soft_delete','=',0)
			->get();

			if(sizeof($fetch_sekolah_asal) > 0){
				$fetch[$i]->sekolah_asal = $fetch_sekolah_asal[0];
				// $fetch[$i]->tingkat_pendidikan_id = $fetch_sekolah_asal[0];
			}else{
				$fetch[$i]->sekolah_asal = [];
			}

			//konfirmasi
			$fetch_konfirmasi = DB::connection('sqlsrv_2')
			->table('ppdb.konfirmasi_pendaftaran')
			->where('calon_peserta_didik_id','=',$fetch[$i]->calon_peserta_didik_id)
			->where('soft_delete','=',0)
			->get();

			if(sizeof($fetch_konfirmasi) > 0){
				$fetch[$i]->status_konfirmasi = $fetch_konfirmasi[0]->status;
				$fetch[$i]->tanggal_konfirmasi = $fetch_konfirmasi[0]->last_update;
			}else{
				$fetch[$i]->status_konfirmasi = 0;
				$fetch[$i]->tanggal_konfirmasi = '-';
			}

			// if(($fetch[$i]->status_terima == 9 || $fetch[$i]->status_terima == 1) && $fetch[$i]->sekolah_penerima == 'sekolah_sama'){
			// 	$diterima++;
			// }

			// $fetch[$i]->jarak = self::distance($fetch[$i]->lintang,$fetch[$i]->bujur,$fetch[$i]->lintang_sekolah,$fetch[$i]->bujur_sekolah);
		}

		return $return = [
			'rows' => $fetch,
			'count' => sizeof($fetch),
			// 'count_diterima' => $diterima,
			'count_diterima' => DB::connection('sqlsrv_2')->select(DB::raw("SELECT sum(case when diterima.status_terima in (1,9) and diterima.sekolah_id = pilihan.sekolah_id then 1 else 0 end) as diterima {$query_body}"))[0]->diterima,
			'countAll' => DB::connection('sqlsrv_2')->select(DB::raw("SELECT sum(1) as total {$query_body}"))[0]->total
		];
	}
	
	public function index(Request $request)
	{
		$limit = $request->limit ? $request->limit : 20;
	    $start = $request->start ? $request->start : 0;
	    $calon_peserta_didik_id = $request->calon_peserta_didik_id ? ($request->calon_peserta_didik_id != 'null' ? $request->calon_peserta_didik_id : null) : null;
	    $searchText = $request->searchText ? $request->searchText : null;
	    $pengguna_id = $request->pengguna_id ? $request->pengguna_id : null;
	    $sekolah_id = $request->sekolah_id ? $request->sekolah_id : null;
	    $urut_pilihan = $request->urut_pilihan ? $request->urut_pilihan : null;
	    $periode_kegiatan_id = $request->periode_kegiatan_id ? $request->periode_kegiatan_id : '2020';
	    $kode_wilayah = $request->kode_wilayah ? $request->kode_wilayah : null;

		$count = CalonPD::where('ppdb.calon_peserta_didik.soft_delete', 0)
			->leftJoin(DB::raw("(SELECT
					calon_peserta_didik_id,
					concat ( LEFT ( sekolah.kode_wilayah, 4 ), '00' ) AS kode_kab,
					ppdb.pilihan_sekolah.jalur_id 
				FROM
					ppdb.pilihan_sekolah
					JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id 
				WHERE
					ppdb.pilihan_sekolah.soft_delete = 0 
					AND sekolah.soft_delete = 0 
					".($kode_wilayah ? "AND LEFT ( sekolah.kode_wilayah, 4 ) = '".substr($kode_wilayah,0,4)."'" : "")." 
				GROUP BY
					calon_peserta_didik_id,
					LEFT ( sekolah.kode_wilayah, 4 ),
					ppdb.pilihan_sekolah.jalur_id) as pilihan_sekolahnya"),'pilihan_sekolahnya.calon_peserta_didik_id','=','ppdb.calon_peserta_didik.calon_peserta_didik_id');

		$calonPDs = CalonPD::where('ppdb.calon_peserta_didik.soft_delete', 0)
			->leftJoin('ppdb.sekolah AS sekolah', 'ppdb.calon_peserta_didik.asal_sekolah_id', '=', 'sekolah.sekolah_id')
			->leftJoin('ref.mst_wilayah AS kec', 'kec.kode_wilayah', '=', 'ppdb.calon_peserta_didik.kode_wilayah_kecamatan')
			->leftJoin('ref.mst_wilayah AS kab', 'kab.kode_wilayah', '=', 'ppdb.calon_peserta_didik.kode_wilayah_kabupaten')
			->leftJoin('ref.mst_wilayah AS prov', 'prov.kode_wilayah', '=', 'ppdb.calon_peserta_didik.kode_wilayah_provinsi')
			->leftJoin('pengguna', 'pengguna.pengguna_id', '=', 'calon_peserta_didik.pengguna_id')
			->leftJoin(DB::raw("(SELECT
				calon_peserta_didik_id,
				concat ( LEFT ( sekolah.kode_wilayah, 4 ), '00' ) AS kode_kab,
				ppdb.pilihan_sekolah.jalur_id 
			FROM
				ppdb.pilihan_sekolah
				JOIN ppdb.sekolah sekolah ON sekolah.sekolah_id = ppdb.pilihan_sekolah.sekolah_id 
			WHERE
				ppdb.pilihan_sekolah.soft_delete = 0 
				AND sekolah.soft_delete = 0 
				".($kode_wilayah ? "AND LEFT ( sekolah.kode_wilayah, 4 ) = '".substr($kode_wilayah,0,4)."'" : "")." 
			GROUP BY
				calon_peserta_didik_id,
				LEFT ( sekolah.kode_wilayah, 4 ),
				ppdb.pilihan_sekolah.jalur_id) as pilihan_sekolahnya"),'pilihan_sekolahnya.calon_peserta_didik_id','=','ppdb.calon_peserta_didik.calon_peserta_didik_id')
	    	->take($limit)
			->skip($start)
			->select(
				'ppdb.calon_peserta_didik.*',
				'sekolah.nama AS sekolah_asal',
				'kec.nama as kecamatan',
				'kab.nama as kabupaten',
				'prov.nama as provinsi',
				'pengguna.nama as nama_pengguna',
				'pilihan_sekolahnya.kode_kab'
			)
			->orderBy('ppdb.calon_peserta_didik.nama', 'ASC');
			// ->orderBy('ppdb.calon_peserta_didik.create_date', 'DESC');

		// if($sekolah_id){
		// 	$calonPDs->join('ppdb.pilihan_sekolah', function($join)
		// 	{
		// 		$join->on('ppdb.pilihan_sekolah.calon_peserta_didik_id','=', 'ppdb.calon_peserta_didik.calon_peserta_didik_id');
		// 		$join->on('peringkat.kuis_id','=', 'pengguna_kuis.kuis_id');
		// 	});
		// }
			
		if($calon_peserta_didik_id){
			$count->where('ppdb.calon_peserta_didik.calon_peserta_didik_id','=',$calon_peserta_didik_id);
			$calonPDs->where('ppdb.calon_peserta_didik.calon_peserta_didik_id','=',$calon_peserta_didik_id);
		}

		if($pengguna_id){
			$count->where('ppdb.calon_peserta_didik.pengguna_id','=',$pengguna_id);
			$calonPDs->where('ppdb.calon_peserta_didik.pengguna_id','=',$pengguna_id);
		}

		if($kode_wilayah){
			$count->where('pilihan_sekolahnya.kode_kab','=',$kode_wilayah);
			$calonPDs->where('pilihan_sekolahnya.kode_kab','=',$kode_wilayah);
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
			// ->leftJoin('ppdb.peserta_didik_diterima as diterima',)
			// LEFT JOIN ppdb.peserta_didik_diterima diterima on diterima.peserta_didik_id = calon.calon_peserta_didik_id and diterima.periode_kegiatan_id = calon.periode_kegiatan_id
			// LEFT JOIN pengguna on pengguna.pengguna_id = calon.pengguna_id
			->leftJoin(
				DB::raw('(
					SELECT ROW_NUMBER
					() OVER (
						PARTITION BY pilihan_sekolah.sekolah_id, pilihan_sekolah.jalur_id
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
				-- ORDER BY
				-- 	pilihan_sekolah.urut_pilihan ASC,
				-- 	COALESCE ( konf.status, 0 ) DESC,
				-- 	konf.last_update ASC,
				-- 	pilihan_sekolah.create_date ASC
				ORDER BY
					pilihan_sekolah.sekolah_id,
					pilihan_sekolah.jalur_id
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

			if($calon_peserta_didik_id){
				//diterima atau belum
				$fetch_foto = DB::connection('sqlsrv_2')
				->table('ppdb.peserta_didik_diterima')
				->where('peserta_didik_id','=',$key->calon_peserta_didik_id)
				->where('periode_kegiatan_id','=',$periode_kegiatan_id)
				// ->whereNotIn('status_terima', array('0'))
				->where('soft_delete','=',0)
				->get();

				if(sizeof($fetch_foto) > 0){
					$calonPDs[$i]->peserta_didik_id_diterima = $fetch_foto[0]->peserta_didik_id;
					$calonPDs[$i]->status_terima = $fetch_foto[0]->status_terima;
					// $calonPDs[$i]->status_terima = $fetch_foto[0]->status_terima;
					// $calonPDs[$i]->tingkat_pendidikan_id = $fetch_foto[0];
				}else{
					$calonPDs[$i]->peserta_didik_id_diterima = null;
					$calonPDs[$i]->status_terima = null;
				}
			}

			//peserta didik diterima
			$fetch_diterima = DB::connection('sqlsrv_2')
			->table('ppdb.peserta_didik_diterima')
			->join('ppdb.sekolah as sekolah','sekolah.sekolah_id','=','ppdb.peserta_didik_diterima.sekolah_id')
			->where('peserta_didik_id','=',$key->calon_peserta_didik_id)
			->where('ppdb.peserta_didik_diterima.soft_delete','=',0)
			// ->whereNotIn('status_terima', array('0'))
			->select(
				'ppdb.peserta_didik_diterima.*',
				'sekolah.nama as nama_sekolah'
			);

			// if($tipe != 'terima'){
			// 	$fetch_diterima->whereNotIn('status_terima',array('1'));
			// }else{
			// 	$fetch_diterima->whereIn('status_terima',array('1'));
			// }

			$fetch_diterima = $fetch_diterima->get();

			if(sizeof($fetch_diterima) > 0){
				$calonPDs[$i]->status_terima = $fetch_diterima[0]->status_terima;
				$calonPDs[$i]->verifikator = $fetch_diterima[0]->nama_sekolah;
			}else{
				$calonPDs[$i]->status_terima = null;
				$calonPDs[$i]->verifikator = null;
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
        $peserta_didik_id = $data['id'];
        $jenis = $data['jenis'];

        if(($file == 'undefined') OR ($file == '')){
            return response()->json(['msg' => 'tidak_ada_file']);
		}
		
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

        $ext = $file->getClientOriginalExtension();
        $name = $file->getClientOriginalName();

        $destinationPath = base_path('/public/assets/berkas');
        $upload = $file->move($destinationPath, $peserta_didik_id."-".$jenis_berkas_id.".".$ext);
        // $upload = $file->move($destinationPath, $peserta_didik_id."-".$jenis_berkas_id."-".$name);

        $msg = $upload ? 'sukses' : 'gagal';

        if($upload){

			// return response(['msg' => $msg, 'filename' => "/assets/berkas/".$peserta_didik_id."-".$jenis_berkas_id."-".$name, 'jenis' => $jenis, 'jenis_berkas_id' => $jenis_berkas_id]);
			return response(['msg' => $msg, 'filename' => "/assets/berkas/".$peserta_didik_id."-".$jenis_berkas_id.".".$ext, 'jenis' => $jenis, 'jenis_berkas_id' => $jenis_berkas_id]);
			// return response(['msg' => $msg, 'filename' => "/assets/berkas/".$name, 'jenis' => $jenis, 'jenis_berkas_id' => $jenis_berkas_id]);

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

	// function distance($lat1, $lon1, $lat2, $lon2) { 
    //     $pi80 = M_PI / 180; 
    //     $lat1 *= $pi80; 
    //     $lon1 *= $pi80; 
    //     $lat2 *= $pi80; 
    //     $lon2 *= $pi80; 
    //     $r = 6372.797; // mean radius of Earth in km 
    //     $dlat = $lat2 - $lat1; 
    //     $dlon = $lon2 - $lon1; 
    //     $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2); 
    //     $c = 2 * atan2(sqrt($a), sqrt(1 - $a)); 
    //     $km = $r * $c; 
    //     //echo ' '.$km; 
    //     return $km; 
	// }
	
	public function distance($lat1, $lon1, $lat2, $lon2, $unit = 'K') {
		if (($lat1 == $lat2) && ($lon1 == $lon2)) {
		  return 0;
		}
		else {
		  $theta = $lon1 - $lon2;
		  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		  $dist = acos($dist);
		  $dist = rad2deg($dist);
		  $miles = $dist * 60 * 1.1515;
		  $unit = strtoupper($unit);
	  
		  if ($unit == "K") {
			return ($miles * 1.609344);
		  } else if ($unit == "N") {
			return ($miles * 0.8684);
		  } else {
			return $miles;
		  }
		}
	}

	public function getSekolahPilihan(Request $request){
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;
		$lintang = $request->input('lintang') ? $request->input('lintang') : null;
		$bujur = $request->input('bujur') ? $request->input('bujur') : null;
	
		$fetch_cek = DB::connection('sqlsrv_2')
			->table('ppdb.pilihan_sekolah')
			->join('ppdb.sekolah as sekolah','sekolah.sekolah_id','=','ppdb.pilihan_sekolah.sekolah_id')
			->join('ref.jalur as jalur','jalur.jalur_id','=','ppdb.pilihan_sekolah.jalur_id')
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
				'prov.nama as provinsi',
				'sekolah.lintang',
				'sekolah.bujur',
				'jalur.nama as jalur'
			)
			->orderBy('urut_pilihan','ASC')
			->get();

			for ($i=0; $i < sizeof($fetch_cek); $i++) { 
				$fetch_cek[$i]->jarak = self::distance($lintang,$bujur,$fetch_cek[$i]->lintang,$fetch_cek[$i]->bujur);
			}
		
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

		}
		
		if($exe){

			if((int)$status == 1){
				$fetch_cek = DB::connection('sqlsrv_2')
				->table('ppdb.peserta_didik_diterima')
				->where('peserta_didik_id','=',$calon_peserta_didik_id)
				// ->where('sekolah_id','=',$sekolah_id)
				->where('periode_kegiatan_id','=','2020')
				// ->where('soft_delete','=',0)
				->get();
	
				if(sizeof($fetch_cek) > 0){
					// sudah ada
					$exe = DB::connection('sqlsrv_2')->table('ppdb.peserta_didik_diterima')
					->where('peserta_didik_id','=',$calon_peserta_didik_id)
					// ->where('sekolah_id','=',$sekolah_id)
					->where('periode_kegiatan_id','=','2020')
					->update([
						'soft_delete' => 1,
						'status_terima' => '0',
						'last_update' => DB::raw('now()::timestamp(0)')
					]);

					$fetch_terima = DB::connection('sqlsrv_2')->table('ppdb.peserta_didik_diterima')
					->where('peserta_didik_id','=',$calon_peserta_didik_id)
					// ->where('sekolah_id','=',$sekolah_id)
					->where('periode_kegiatan_id','=','2020')
					->get();
				}else{

					$fetch_terima = array();
					// // belum ada
					// $exe = DB::connection('sqlsrv_2')->table('ppdb.peserta_didik_diterima')
					// ->insert([
					// 	'peserta_didik_id' =>  $calon_peserta_didik_id,
					// 	'periode_kegiatan_id' => '2020',
					// 	'status_terima' => 0,
					// 	'pengguna_id' => null,
					// 	'create_date' => DB::raw('now()::timestamp(0)'),
					// 	'last_update' => DB::raw('now()::timestamp(0)'),
					// 	'soft_delete' => 1,
					// 	'sekolah_id' => null
					// ]);
				}
			}else{
				//do nothing
				$fetch_terima = array();
			}

			return response([ 'success' => true, 'pd_diterima' => $fetch_terima ], 201);
		}else{
			return response([ 'success' => false, 'pd_diterima' => $fetch_terima ], 201);
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
			// ->where('soft_delete','=',0)
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
				"lintang" => str_replace(",",".",$request->input('lintang')), 
				"bujur" => str_replace(",",".",$request->input('bujur')), 
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
				"lintang" => str_replace(",",".",$request->input('lintang')), 
				"bujur" => str_replace(",",".",$request->input('bujur')), 
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
						PARTITION BY pilihan_sekolah.sekolah_id, pilihan_sekolah.jalur_id
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
				-- ORDER BY
				-- 	pilihan_sekolah.urut_pilihan ASC,
				-- 	COALESCE ( konf.status, 0 ) DESC,
				-- 	konf.last_update ASC,
				-- 	pilihan_sekolah.create_date ASC
				ORDER BY
					pilihan_sekolah.sekolah_id,
					pilihan_sekolah.jalur_id
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

			// return $urutan;die;

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

		// return $calon_pd;die;

		$arrBulan = [
			'Januari',
			'Februari',
			'Maret',
			'April',
			'Mei',
			'Juni',
			'Juli',
			'Agustus',
			'September',
			'Oktober',
			'November',
			'Desember'
		];

    	$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template_formulir_pendaftaran.docx');

    	$orangtua = $calon_pd->orang_tua_utama;

		$templateProcessor->setValue('nik', $calon_pd->nik);
		$templateProcessor->setValue('no_npsn1', substr(@$pilihan_sekolah[0]->npsn,0,1));
		$templateProcessor->setValue('no_npsn2', substr(@$pilihan_sekolah[0]->npsn,1,1));
		$templateProcessor->setValue('no_npsn3', substr(@$pilihan_sekolah[0]->npsn,2,1));
		$templateProcessor->setValue('no_npsn4', substr(@$pilihan_sekolah[0]->npsn,3,1));
		$templateProcessor->setValue('no_npsn5', substr(@$pilihan_sekolah[0]->npsn,4,1));
		$templateProcessor->setValue('no_npsn6', substr(@$pilihan_sekolah[0]->npsn,5,1));
		$templateProcessor->setValue('no_npsn7', substr(@$pilihan_sekolah[0]->npsn,6,1));
		$templateProcessor->setValue('no_npsn8', substr(@$pilihan_sekolah[0]->npsn,7,1));
		$templateProcessor->setValue('no_jalur1', substr(@$pilihan_sekolah[0]->jalur_id,0,1));
		$templateProcessor->setValue('no_jalur2', substr(@$pilihan_sekolah[0]->jalur_id,1,1));
		$templateProcessor->setValue('no_jalur3', substr(@$pilihan_sekolah[0]->jalur_id,2,1));
		$templateProcessor->setValue('no_jalur4', substr(@$pilihan_sekolah[0]->jalur_id,3,1));
		$templateProcessor->setValue('no1', substr($urutan, 0, 1));
		$templateProcessor->setValue('no2', substr($urutan, 1, 1));
		$templateProcessor->setValue('no3', substr($urutan, 2, 1));
		$templateProcessor->setValue('no4', substr($urutan, 3, 1));
		$templateProcessor->setValue('nama', $calon_pd->nama);
		$templateProcessor->setValue('jenis_kelamin', $calon_pd->jenis_kelamin == 'L' ? 'Laki - laki' : 'Perempuan');
		$templateProcessor->setValue('tempat_lahir', $calon_pd->tempat_lahir);
		$templateProcessor->setValue('tgllhrd', date("d", strtotime($calon_pd->tanggal_lahir)));
		$templateProcessor->setValue('tgllhrm', date("m", strtotime($calon_pd->tanggal_lahir)));
		$templateProcessor->setValue('tgllhry', date("Y", strtotime($calon_pd->tanggal_lahir)));
		$templateProcessor->setValue('asal_sekolah', $calon_pd->asal_sekolah);
		$templateProcessor->setValue('alamat_jalan', $calon_pd->alamat_tempat_tinggal);
		$templateProcessor->setValue('rt', $calon_pd->rt);
		$templateProcessor->setValue('rw', $calon_pd->rw);
		$templateProcessor->setValue('desa', '');
		$templateProcessor->setValue('dusun', $calon_pd->dusun);
		$templateProcessor->setValue('desa', $calon_pd->desa_kelurahan);
		$templateProcessor->setValue('kecamatan', $calon_pd->kecamatan);
		$templateProcessor->setValue('kabupaten', $calon_pd->kabupaten);
		$templateProcessor->setValue('provinsi', $calon_pd->provinsi);
		$templateProcessor->setValue('lintang', $calon_pd->lintang);
		$templateProcessor->setValue('bujur', $calon_pd->bujur);
		$templateProcessor->setValue('jalur', @$pilihan_sekolah[0]->nama_jalur);
		$templateProcessor->setValue('npsn1', @$pilihan_sekolah[0]->npsn);
		$templateProcessor->setValue('sekolah1', @$pilihan_sekolah[0]->nama_sekolah);
		$templateProcessor->setValue('npsn2', @$pilihan_sekolah[1]->npsn);
		$templateProcessor->setValue('sekolah2', @$pilihan_sekolah[1]->nama_sekolah);
		$templateProcessor->setValue('npsn3', @$pilihan_sekolah[2]->npsn);
		$templateProcessor->setValue('sekolah3', @$pilihan_sekolah[2]->nama_sekolah);
		$templateProcessor->setValue('orang_tua_utama', $calon_pd['nama_'.$orangtua]);
		$templateProcessor->setValue('orang_tua_tempat_lahir', $calon_pd->tempat_lahir_ayah);
		$templateProcessor->setValue('orttd', date("d", strtotime( $calon_pd['tanggal_lahir_'.$orangtua] )));
		$templateProcessor->setValue('orttm', date("m", strtotime( $calon_pd['tanggal_lahir_'.$orangtua] )));
		$templateProcessor->setValue('ortty', date("Y", strtotime( $calon_pd['tanggal_lahir_'.$orangtua] )));
		$templateProcessor->setValue('orang_tua_pendidikan', $calon_pd['pendidikan_terakhir_'.$orangtua]);
		$templateProcessor->setValue('orang_tua_pekerjaan', $calon_pd['pekerjaan_'.$orangtua]);
		$templateProcessor->setValue('orang_tua_alamat_tempat_tinggal', $calon_pd['alamat_tempat_tinggal_'.$orangtua]);
		$templateProcessor->setValue('orang_tua_no_telepon', $calon_pd['no_telepon_'.$orangtua]);
		$templateProcessor->setValue('datenow', date("d") . " " . $arrBulan[(int)date("m")-1] . " " . date("Y"));
		// $templateProcessor->setValue('datenow', date("d M Y"));

		header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="Formulir_PPDB_'.date("Y").'-'.$calon_pd->nik.'.docx"');
        $templateProcessor->saveAs('php://output');

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
						PARTITION BY pilihan_sekolah.sekolah_id, pilihan_sekolah.jalur_id
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
				-- ORDER BY
				-- 	pilihan_sekolah.urut_pilihan ASC,
				-- 	COALESCE ( konf.status, 0 ) DESC,
				-- 	konf.last_update ASC,
				-- 	pilihan_sekolah.create_date ASC
				ORDER BY
					pilihan_sekolah.sekolah_id,
					pilihan_sekolah.jalur_id
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

		// return $calon_pd;die;

		$arrBulan = [
			'Januari',
			'Februari',
			'Maret',
			'April',
			'Mei',
			'Juni',
			'Juli',
			'Agustus',
			'September',
			'Oktober',
			'November',
			'Desember'
		];

		$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor('template_bukti_pendaftaran.docx');

		$templateProcessor->setValue('no_npsn1', substr(@$pilihan_sekolah[0]->npsn,0,1));
		$templateProcessor->setValue('no_npsn2', substr(@$pilihan_sekolah[0]->npsn,1,1));
		$templateProcessor->setValue('no_npsn3', substr(@$pilihan_sekolah[0]->npsn,2,1));
		$templateProcessor->setValue('no_npsn4', substr(@$pilihan_sekolah[0]->npsn,3,1));
		$templateProcessor->setValue('no_npsn5', substr(@$pilihan_sekolah[0]->npsn,4,1));
		$templateProcessor->setValue('no_npsn6', substr(@$pilihan_sekolah[0]->npsn,5,1));
		$templateProcessor->setValue('no_npsn7', substr(@$pilihan_sekolah[0]->npsn,6,1));
		$templateProcessor->setValue('no_npsn8', substr(@$pilihan_sekolah[0]->npsn,7,1));
		$templateProcessor->setValue('no_jalur1', substr(@$pilihan_sekolah[0]->jalur_id,0,1));
		$templateProcessor->setValue('no_jalur2', substr(@$pilihan_sekolah[0]->jalur_id,1,1));
		$templateProcessor->setValue('no_jalur3', substr(@$pilihan_sekolah[0]->jalur_id,2,1));
		$templateProcessor->setValue('no_jalur4', substr(@$pilihan_sekolah[0]->jalur_id,3,1));
        $templateProcessor->setValue('no1', substr($urutan, 0, 1));
		$templateProcessor->setValue('no2', substr($urutan, 1, 1));
		$templateProcessor->setValue('no3', substr($urutan, 2, 1));
		$templateProcessor->setValue('no4', substr($urutan, 3, 1));
		$templateProcessor->setValue('nik', $calon_pd->nik);
		$templateProcessor->setValue('nama', $calon_pd->nama);
		$templateProcessor->setValue('nisn', $calon_pd->nisn);
		$templateProcessor->setValue('tempat_lahir', $calon_pd->tempat_lahir);
		$templateProcessor->setValue('alamat_jalan', $calon_pd->alamat_tempat_tinggal);
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
		$templateProcessor->setValue('datenow', date("d") . " " . $arrBulan[(int)date("m")-1] . " " . date("Y"));
		// $templateProcessor->setValue('datenow', date("F Y"));
        $templateProcessor->setImageValue('codeQR', array('path' => "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={$calon_pd->nik}", 'width' => '1in', 'height' => '1in'));


        // $templateProcessor->deleteBlock('DELETEME');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="Bukti_PPDB_'.date("Y").'-'.$calon_pd->nik.'.docx"');
        $templateProcessor->saveAs('php://output');
	}

	static function validasiBerkas(Request $request){
		$calon_peserta_didik_id = $request->input('calon_peserta_didik_id') ? $request->input('calon_peserta_didik_id') : null;

		if($calon_peserta_didik_id){
			$fetch = DB::connection('sqlsrv_2')->select(DB::raw("SELECT
				berkas.nama_file,
				pilihan.calon_peserta_didik_id,
				jalur_berkas.* 
			FROM
				REF.jalur_berkas jalur_berkas
				JOIN ppdb.pilihan_sekolah pilihan ON pilihan.jalur_id = jalur_berkas.jalur_id 
				AND pilihan.calon_peserta_didik_id = '".$calon_peserta_didik_id."' 
				AND pilihan.soft_delete = 0 
				AND pilihan.urut_pilihan = 1
				LEFT JOIN ppdb.berkas_calon berkas ON berkas.calon_peserta_didik_id = pilihan.calon_peserta_didik_id 
				AND berkas.soft_delete = 0 
				AND berkas.jenis_berkas_id = jalur_berkas.jenis_berkas_id 
			WHERE
				expired_date IS NULL"));

			return response([ 'rows' => $fetch, 'count' => sizeof($fetch) ], 200);			

		}else{
			return response([ 'rows' => array(), 'count' => 0 ], 201);
		}
	}
}
