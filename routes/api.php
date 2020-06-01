<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
// Route::post('Auth/login', 'PenggunaController@authenticate');
// Route::prefix('Buku')->group(function () {
	// });

Route::prefix('Beranda')->group(function(){
	Route::post('beranda_dinas', 'DashboardController@beranda_dinas');
	Route::post('beranda_sekolah', 'DashboardController@beranda_sekolah');
});

Route::prefix('Pertanyaan')->group(function () {
	Route::post('simpanPantauan', 'PertanyaanController@simpanPantauan');
	Route::post('simpanPertanyaan', 'PertanyaanController@simpanPertanyaan');
	Route::post('getPertanyaan', 'PertanyaanController@getPertanyaan');
	Route::post('getPertanyaanPantauan', 'PertanyaanController@getPertanyaanPantauan');
	Route::post('simpanJawaban', 'PertanyaanController@simpanJawaban');
	Route::post('getJawaban', 'PertanyaanController@getJawaban');
	Route::post('simpanKomentar', 'PertanyaanController@simpanKomentar');
	Route::post('simpanDukungan', 'PertanyaanController@simpanDukungan');
});

Route::prefix('Notifikasi')->group(function () {
	Route::post('simpanNotifikasi', 'NotifikasiController@simpanNotifikasi');
	Route::post('getNotifikasi', 'NotifikasiController@getNotifikasi');
});

Route::prefix('Kuis')->group(function () {
	Route::post('generateUUID', 'KuisController@generateUUID');
	Route::post('getKuis', 'KuisController@getKuis');
	Route::post('getPertanyaanKuis', 'KuisController@getPertanyaanKuis');
	Route::post('simpanKuis', 'KuisController@simpanKuis');
	Route::post('getPenggunaKuis', 'KuisController@getPenggunaKuis');
	Route::post('simpanPenggunaKuis', 'KuisController@simpanPenggunaKuis');
	Route::post('simpanJawabanKuis', 'KuisController@simpanJawabanKuis');
	Route::post('getKuisDiikuti', 'KuisController@getKuisDiikuti');
	Route::post('getKuisRuang', 'KuisController@getKuisRuang');
});

Route::prefix('Ruang')->group(function () {
	Route::post('simpanRuang', 'RuangController@simpanRuang');
	Route::post('simpanPertanyaanRuang', 'RuangController@simpanPertanyaanRuang');
	Route::post('simpanPenggunaRuang', 'RuangController@simpanPenggunaRuang');
	Route::post('getRuang', 'RuangController@getRuang');
	Route::post('getPenggunaRuang', 'RuangController@getPenggunaRuang');
	Route::post('upload', 'RuangController@upload');
	Route::post('getRuangDiikuti', 'RuangController@getRuangDiikuti');
});


Route::prefix('Ref')->group(function () {
	Route::post('getJenjang', 'RefController@getJenjang');
	Route::post('getTingkatPendidikan', 'RefController@getTingkatPendidikan');
	Route::post('getMataPelajaran', 'RefController@getMataPelajaran');
	Route::get('getJalur', 'RefController@getJalur');
	Route::post('mst_wilayah', 'RefController@getmst_wilayah');
});

Route::prefix('Otentikasi')->group(function () {
	Route::post('masuk', 'PenggunaController@authenticate');
	Route::post('getPengguna', 'PenggunaController@getPengguna');
	Route::get('getPengguna', 'PenggunaController@getPengguna');
	Route::post('simpanPengguna', 'PenggunaController@simpanPengguna');
	Route::post('buatPengguna', 'PenggunaController@buatPengguna');
	Route::post('upload', 'PenggunaController@upload');
});

Route::prefix('PesertaDidik')->group(function(){
	Route::get('get', 'PesertaDidikController@index'); // params: { limit,offset,searchText(nisn,nama, nik) }
});

Route::prefix('Sekolah')->group(function(){
	Route::get('get', 'SekolahController@index'); // params: { limit,offset,searchText(nisn,nama) }
	Route::post('getCalon', 'SekolahController@getCalonPDSekolah'); 
});

Route::prefix('Pilihsekolah')->group(function(){
	Route::get('get', 'PilihsekolahController@index'); // params: { limit,offset) }
	Route::post('save', 'PilihsekolahController@store'); // params: { 'kolom2_pilihan_sekolah' }
	Route::get('delete/{id}', 'PilihsekolahController@destroy'); // .../delete/a7109cd3-8307-4647-9608-2b665df3ba9f
});

Route::prefix('CalonPesertaDidik')->group(function(){
	Route::get('get', 'CalonPesertaDidikController@index'); // params: { limit,offset, calon_peserta_didik_id, searchText:(nik) }
	Route::post('save', 'CalonPesertaDidikController@store'); // params: { 'kolom2_calon_pd' }
	Route::post('simpanLintangBujur', 'CalonPesertaDidikController@simpanLintangBujur'); // params: { 'kolom2_calon_pd' }
	Route::get('delete/{id}', 'CalonPesertaDidikController@destroy'); // .../delete/a7109cd3-8307-4647-9608-2b665df3ba9f
	Route::get('print/formulir/{id}', 'CalonPesertaDidikController@print_formulir'); // .../print/a7109cd3-8307-4647-9608-2b665df3ba9f
	Route::get('print/bukti/{id}', 'CalonPesertaDidikController@print_bukti'); // .../print/a7109cd3-8307-4647-9608-2b665df3ba9f
	Route::post('importDariPesertaDidikDapodik', 'CalonPesertaDidikController@importDariPesertaDidikDapodik'); // params: { 'kolom2_calon_pd' }
	Route::post('simpanSekolahPilihan', 'CalonPesertaDidikController@simpanSekolahPilihan'); // params: { 'kolom2_calon_pd' }
	Route::get('getSekolahPilihan', 'CalonPesertaDidikController@getSekolahPilihan'); // params: { 'kolom2_calon_pd' }
	Route::get('hapusSekolahPilihan', 'CalonPesertaDidikController@hapusSekolahPilihan'); // params: { 'kolom2_calon_pd' }
	Route::post('upload', 'CalonPesertaDidikController@upload');
	Route::post('upload/{id}/{jenis}', 'CalonPesertaDidikController@upload');
	Route::post('simpanBerkasCalon', 'CalonPesertaDidikController@simpanBerkasCalon'); // params: { 'kolom2_calon_pd' }
	Route::get('getBerkasCalon', 'CalonPesertaDidikController@getBerkasCalon'); // params: { 'kolom2_calon_pd' }
	Route::post('simpanKonfirmasiPendaftaran', 'CalonPesertaDidikController@simpanKonfirmasiPendaftaran'); // params: { 'kolom2_calon_pd' }
	Route::get('getKonfirmasiPendaftaran', 'CalonPesertaDidikController@getKonfirmasiPendaftaran'); // params: { 'kolom2_calon_pd' }
	Route::get('cekNik', 'CalonPesertaDidikController@cekNik'); // params: { 'kolom2_calon_pd' }
	Route::get('cekNISN', 'CalonPesertaDidikController@cekNISN'); // params: { 'kolom2_calon_pd' }
	Route::get('validasiBerkas', 'CalonPesertaDidikController@validasiBerkas'); // params: { 'kolom2_calon_pd' }
	Route::post('batalkanKonfirmasi', 'CalonPesertaDidikController@batalkanKonfirmasi'); // params: { 'kolom2_calon_pd' }
	Route::get('getRekapTotal', 'CalonPesertaDidikController@getRekapTotal'); // params: { 'kolom2_calon_pd' }
	Route::get('getCalonPesertaDidikSekolah', 'CalonPesertaDidikController@getCalonPesertaDidikSekolah'); // params: { 'kolom2_calon_pd' }
});

Route::prefix('BerkasCalon')->group(function(){
	Route::get('get', 'BerkasCalonController@index'); // params: { limit,offset) }
});

Route::prefix('JadwalKegiatan')->group(function(){
	Route::post('get', 'JadwalKegiatanController@index');
	Route::post('simpanLintangBujur', 'JadwalKegiatanController@store');
	Route::get('delete/{id}', 'JadwalKegiatanController@destroy');
	Route::get('beranda', 'JadwalKegiatanController@beranda');
});

Route::prefix('Kuota')->group(function(){
	Route::post('sekolah', 'KuotaController@sekolah');
	Route::post('save', 'KuotaController@save');
});

Route::get('/clear-cache', function() {
    Artisan::call('cache:clear');
    return "Cache is cleared";
});

Route::prefix('app')->group(function () {
	Route::post('getWilayah', 'AppController@getWilayah');
	Route::post('getWilayahKlien', 'AppController@getWilayahKlien');
	Route::post('getBerkasJalur', 'AppController@getBerkasJalur');
	Route::post('getGeoJsonBasic', 'AppController@getGeoJsonBasic');
});

Route::middleware('token')->group(function(){
	Route::options('{any}', function($any){ return Response('OK', 200); });
	Route::options('{a}/{b}', function($a, $b){ return Response('OK', 200); });
	Route::options('{a}/{b}/{c}', function($a,$b,$c){ return Response('OK', 200); });
	Route::options('{a}/{b}/{c}/{d}', function($a,$b,$c,$d){ return Response('OK', 200); });
	Route::options('{a}/{b}/{c}/{d}/{e}', function($a,$b,$c,$d,$e){ return Response('OK', 200); });
});

Route::options('{any}', function($any){ return Response('OK', 200); });
Route::options('{a}/{b}', function($a, $b){ return Response('OK', 200); });
Route::options('{a}/{b}/{c}', function($a,$b,$c){ return Response('OK', 200); });
Route::options('{a}/{b}/{c}/{d}', function($a,$b,$c,$d){ return Response('OK', 200); });
Route::options('{a}/{b}/{c}/{d}/{e}', function($a,$b,$c,$d,$e){ return Response('OK', 200); });
