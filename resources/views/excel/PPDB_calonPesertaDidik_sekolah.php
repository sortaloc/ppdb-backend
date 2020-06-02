<?php
$daftarPengawas = $return['rows'];
$Sekolah = $sekolah;
$headerTable = 5;
$logo = "lumajang.png";

/** PHPExcel */
require_once ('Classes/PHPExcel.php');
// Create new PHPExcel object
$object = new PHPExcel();

//style untuk row
$style_row = array(
  'alignment' => array(
    'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER // Set text jadi di tengah secara vertical (middle)
  ),
  'borders' => array(
      'allborders' => array(
          'style' => PHPExcel_Style_Border::BORDER_THIN,
          'color' => array('rgb' => '000000')
      )
  )
);

$object->getActiveSheet()->getPageSetup()->setPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);
$object->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
$object->getActiveSheet()->getPageSetup()->setFitToWidth(1);

//set FontSize
$object->getActiveSheet()->getStyle("A1")->getFont()->setSize(14);

// Style
$style_header = array(
  'fill' => array(
      'type' => PHPExcel_Style_Fill::FILL_SOLID,
      'color' => array('rgb' => 'A6A6A6')
  ),
  'font'  => array(
      'color' => array('rgb' => '000000'),
  ),
  'alignment' => array(
    'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER // Set text jadi di tengah secara vertical (middle)
  ),
);

$table_header = array(
  'fill' => array(
      'type' => PHPExcel_Style_Fill::FILL_SOLID,
      'color' => array('rgb' => '92D050')
  ),
);

// $object->getActiveSheet()->getStyle('A1:N1')->applyFromArray($style_header);
$object->getActiveSheet()->getStyle('A'.$headerTable.':N'.$headerTable)->applyFromArray($table_header);

//set Width Colum
$object->getActiveSheet()->getColumnDimension('A')->setWidth(7);
$object->getActiveSheet()->getColumnDimension('B')->setWidth(20);
$object->getActiveSheet()->getColumnDimension('C')->setWidth(18);
$object->getActiveSheet()->getColumnDimension('D')->setWidth(17);
$object->getActiveSheet()->getColumnDimension('E')->setWidth(3);
$object->getActiveSheet()->getColumnDimension('F')->setWidth(13);
$object->getActiveSheet()->getColumnDimension('G')->setWidth(12);
$object->getActiveSheet()->getColumnDimension('H')->setWidth(9);
$object->getActiveSheet()->getColumnDimension('I')->setWidth(7);
$object->getActiveSheet()->getColumnDimension('J')->setWidth(9);
$object->getActiveSheet()->getColumnDimension('K')->setWidth(11);
$object->getActiveSheet()->getColumnDimension('L')->setWidth(18);
$object->getActiveSheet()->getColumnDimension('M')->setWidth(15);
$object->getActiveSheet()->getColumnDimension('N')->setWidth(14);

//set Height Row
// $object->getActiveSheet()->getRowDimension('4')->setRowHeight(8);

//set font bold
$object->getActiveSheet()->getStyle('B2')->getFont()->setBold(true);
$object->getActiveSheet()->getStyle('A'.$headerTable.':N'.$headerTable)->getFont()->setBold(true);

//set margeCell
$object->getActiveSheet()->mergeCells('B1:N1');
$object->getActiveSheet()->mergeCells('B2:N2');
$object->getActiveSheet()->mergeCells('B3:N3');
$object->getActiveSheet()->mergeCells('B4:N4');

//set colum text center
$object->getActiveSheet()->getStyle('A')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
$object->getActiveSheet()->getStyle('A'.$headerTable.':N'.$headerTable)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

//set colom text LEFT
// $object->getActiveSheet()->getStyle('A1:C10')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);

$ex = $object->setActiveSheetIndex(0);

//Add a drawing to the worksheet
$objDrawing = new PHPExcel_Worksheet_Drawing();
$objDrawing->setName('Logo');
$objDrawing->setDescription('Logo');
$objDrawing->setPath(__DIR__.'/img/lumajang.png');
$objDrawing->setCoordinates('A1');
$objDrawing->setHeight(62);
// $objDrawing->setWidth(650);
$objDrawing->setOffsetX(3);
$objDrawing->setOffsetY(10);
$objDrawing->setWorksheet($object->getActiveSheet());

$object->setActiveSheetIndex(0)
->setCellValue('B1', 'DAFTAR CALON PESERTA DIDIK BARU KAB. LUMAJANG TAHUN AJARAN 2020/2021')
->setCellValue('B2', $sekolah->nama)
->setCellValue('B3', 'Alamat: '.$sekolah->alamat_jalan." ".$sekolah->kabupaten." ".$sekolah->kecamatan)
->setCellValue('B4', 'KUOTA :  '.$sekolah->kuota.' Siswa (1 Rombel)')
->setCellValue('A'.$headerTable, 'No')
->setCellValue('B'.$headerTable, 'Nama Siswa')
->setCellValue('C'.$headerTable, 'NIK')
->setCellValue('D'.$headerTable, 'No Urut Daftar')
->setCellValue('E'.$headerTable, 'JK')
->setCellValue('F'.$headerTable, 'Tempat Lahir')
->setCellValue('G'.$headerTable, 'Tanggal Lahir')
->setCellValue('H'.$headerTable, 'Jarak ke Sekolah (meter)')
->setCellValue('I'.$headerTable, 'Pilihan')
->setCellValue('J'.$headerTable, 'Rangking Diterima')
->setCellValue('K'.$headerTable, 'Tanggal Disetujui')
->setCellValue('L'.$headerTable, 'Status')
->setCellValue('M'.$headerTable, 'Orangtua')
->setCellValue('N'.$headerTable, 'Kontak Orangtua')
;

$object->getActiveSheet()->getStyle('A'.$headerTable.':N'.$headerTable)->applyFromArray($style_row);

//Table
$num = $headerTable + 1;
$no='1';
foreach ($daftarPengawas as $value) {
  $ortu = $value->orang_tua_utama;
  $nama_orangtua = 'nama_'.$ortu;
  $no_telepon_orangtua = 'no_telepon_'.$ortu;

  $ex->setCellValue("A".$num, $no);
  $ex->setCellValue("B".$num, $value->nama);
  $ex->setCellValue("C".$num, $value->nik);
  $ex->setCellValue("D".$num, $value->urutan);
  $ex->setCellValue("E".$num, $value->jenis_kelamin);
  $ex->setCellValue("F".$num, $value->tempat_lahir);
  $ex->setCellValue("G".$num, date("d/m/Y", strtotime($value->tanggal_lahir)));
  $ex->setCellValue("H".$num, intval($value->jarak));
  $ex->setCellValue("I".$num, $value->urut_pilihan);
  $ex->setCellValue("J".$num, "-");
  $ex->setCellValue("K".$num, date("d/m/Y", strtotime($value->tanggal_konfirmasi)));
  $ex->setCellValue("L".$num, $value->status_terima !== null ? $value->status_terima : "-");
  $ex->setCellValue("M".$num, $value->$nama_orangtua);
  $ex->setCellValue("N".$num, $value->$no_telepon_orangtua);

  $object->getActiveSheet()->getStyle('A'.$num.':N'.$num)->applyFromArray($style_row);
  $object->getActiveSheet()->getStyle('A'.$num.':N'.$num)->getFont()->setSize(9);

  $object->getActiveSheet()->getStyle('A'.$num.':N'.$num)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
  $object->getActiveSheet()->getStyle('B'.$num)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
  $object->getActiveSheet()->getStyle("C".$num)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
  $object->getActiveSheet()->getStyle("C".$num)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER);

  $num ++;
  $no++;
}

$object->getActiveSheet()->getStyle('A1:N'.$object->getActiveSheet()->getHighestRow())->getAlignment()->setWrapText(true); 

// /--------------

// Set active sheet index to the first sheet, so Excel opens this as the first sheet
$object->setActiveSheetIndex(0);

// Redirect output to a client’s web browser (Excel2007)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="CalonPDB2020_.xlsx"');
$data = PHPExcel_IOFactory::createWriter($object, 'Excel2007');
$data->setIncludeCharts(true);
$data->save('php://output');
exit;
?>